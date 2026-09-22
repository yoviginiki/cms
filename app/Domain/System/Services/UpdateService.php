<?php

namespace App\Domain\System\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Installation self-update (hardened per audit 2026-09-22, F02).
 *
 * Trust comes from the operator's configuration, never from the request:
 *  - the package must be fetched over https from the configured update
 *    server host (no redirects, bounded size);
 *  - the release checksum must carry an Ed25519 signature that verifies
 *    with cms.updates.public_key (verified BEFORE any download);
 *  - every archive entry is checked before a single byte is copied: no
 *    traversal, no absolute paths, no symlinks, only allow-listed paths,
 *    never protected paths;
 *  - a failed migration restores the previous files and is reported as a
 *    failed update, not logged and forgotten.
 */
class UpdateService
{
    private string $updateServer;
    private string $currentVersion;
    private string $basePath;
    private string $updateDir;
    /** @var \Closure(): void */
    private \Closure $migrator;

    public function __construct(?string $basePath = null, ?string $updateDir = null, ?\Closure $migrator = null)
    {
        $this->updateServer = (string) config('cms.updates.server', 'https://updates.ensodo.eu');
        $this->currentVersion = (string) config('cms.version', '1.0.0');
        $this->basePath = rtrim($basePath ?? base_path(), '/');
        $this->updateDir = rtrim($updateDir ?? storage_path('app/updates'), '/');
        $this->migrator = $migrator ?? function (): void {
            Artisan::call('migrate', ['--force' => true]);
        };
    }

    /**
     * Check for available updates.
     */
    public function checkForUpdates(): ?array
    {
        $cacheKey = 'cms_update_check';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::timeout(10)->get("{$this->updateServer}/api/v1/check", [
                'version' => $this->currentVersion,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = !empty($data['available']) ? $data : null;
                Cache::put($cacheKey, $result ?? false, now()->addHours(24));
                return $result;
            }
        } catch (\Throwable $e) {
            Log::warning('Update check failed', ['error' => $e->getMessage()]);
        }

        Cache::put($cacheKey, false, now()->addHours(1)); // retry in 1h on failure
        return null;
    }

    // ---- request-time gates (no network) ------------------------------------

    /** Strict release version: used in a file name, so it must be a plain semver-ish token. */
    public function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d{1,5}\.\d{1,5}\.\d{1,5}(?:[-+][A-Za-z0-9.]{1,32})?$/', $version);
    }

    /** https URL on the configured update server host, without credentials. */
    public function isAllowedSource(string $url): bool
    {
        $parts = parse_url($url);
        $server = parse_url($this->updateServer);
        if (!is_array($parts) || !is_array($server)) {
            return false;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = strtolower((string) ($server['host'] ?? ''));

        return $host !== '' && $host === $allowed;
    }

    /** "sha256:<64 hex>" */
    public function isValidChecksum(string $checksum): bool
    {
        return (bool) preg_match('/^sha256:[a-f0-9]{64}$/', $checksum);
    }

    /**
     * Verify the Ed25519 release signature over the checksum string with the
     * operator-configured public key. False when no key is configured.
     */
    public function verifySignature(string $checksum, string $signatureB64): bool
    {
        $keyB64 = (string) config('cms.updates.public_key', '');
        if ($keyB64 === '' || !function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        $key = base64_decode($keyB64, true);
        $sig = base64_decode($signatureB64, true);
        if ($key === false || $sig === false
            || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $checksum, $key);
        } catch (\Throwable) {
            return false;
        }
    }

    // ---- download + apply -----------------------------------------------------

    /**
     * Download an update package. Callers must have passed the gates above
     * (the controller does); this re-checks them so a programmatic caller
     * cannot skip them.
     */
    public function downloadUpdate(string $version, string $downloadUrl, string $expectedChecksum): string
    {
        if (!$this->isValidVersion($version)) {
            throw new \RuntimeException('Invalid release version.');
        }
        if (!$this->isAllowedSource($downloadUrl)) {
            throw new \RuntimeException('Update source is not the configured update server.');
        }
        if (!$this->isValidChecksum($expectedChecksum)) {
            throw new \RuntimeException('Invalid checksum format.');
        }

        File::ensureDirectoryExists($this->updateDir);
        $zipPath = "{$this->updateDir}/cms-{$version}.zip";
        $maxBytes = (int) config('cms.updates.max_package_bytes', 200 * 1024 * 1024);

        $response = Http::timeout(120)
            ->withOptions(['sink' => $zipPath, 'allow_redirects' => false])
            ->get($downloadUrl);
        if (!$response->successful()) {
            File::delete($zipPath);
            throw new \RuntimeException("Failed to download update (HTTP {$response->status()})");
        }
        if (!is_file($zipPath) || filesize($zipPath) > $maxBytes) {
            File::delete($zipPath);
            throw new \RuntimeException('Update package exceeds the configured size limit.');
        }

        // Verify checksum
        $actualChecksum = 'sha256:' . hash_file('sha256', $zipPath);
        if (!hash_equals($expectedChecksum, $actualChecksum)) {
            File::delete($zipPath);
            throw new \RuntimeException('Checksum mismatch — the package is not the signed release.');
        }

        return $zipPath;
    }

    /**
     * Apply an update from a downloaded ZIP. Validates the whole archive
     * first; nothing under the application root changes unless every entry
     * is acceptable.
     */
    public function applyUpdate(string $zipPath): array
    {
        File::ensureDirectoryExists($this->updateDir);
        $lock = fopen("{$this->updateDir}/.apply.lock", 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Another update is already in progress.');
        }

        try {
            return $this->applyLocked($zipPath);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function applyLocked(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open update archive');
        }

        // ---- pre-flight: every entry must be acceptable ----------------------
        $rootPrefix = $this->detectSingleRootDir($zip);
        $entries = [];
        $maxBytes = (int) config('cms.updates.max_package_bytes', 200 * 1024 * 1024);
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                throw new \RuntimeException("Unreadable archive entry #{$i}");
            }
            $name = (string) $stat['name'];
            $rel = $rootPrefix !== null && str_starts_with($name, $rootPrefix) ? substr($name, strlen($rootPrefix)) : $name;
            $isDir = str_ends_with($rel, '/');
            $rel = rtrim($rel, '/');
            if ($rel === '') {
                continue;
            }
            $opsys = $attr = null;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr)
                && $opsys === ZipArchive::OPSYS_UNIX
                && $this->isSymlinkEntry((int) $attr)) {
                $zip->close();
                throw new \RuntimeException("Archive entry '{$name}' is a symlink — refused.");
            }
            $error = $this->relativePathError($rel);
            if ($error !== null) {
                $zip->close();
                throw new \RuntimeException("Archive entry '{$name}': {$error}");
            }
            $total += (int) ($stat['size'] ?? 0);
            if ($total > $maxBytes) {
                $zip->close();
                throw new \RuntimeException('Update package exceeds the configured size limit when extracted.');
            }
            $entries[] = ['index' => $i, 'rel' => $rel, 'dir' => $isDir];
        }

        // ---- extract into a fresh private dir, then copy over ------------------
        $extractDir = "{$this->updateDir}/extract-" . bin2hex(random_bytes(6));
        $backupDir = "{$this->updateDir}/backup-{$this->currentVersion}-" . date('YmdHis');
        File::ensureDirectoryExists($extractDir);
        File::ensureDirectoryExists($backupDir);

        $updatedFiles = [];
        try {
            foreach ($entries as $entry) {
                $dest = "{$extractDir}/{$entry['rel']}";
                if ($entry['dir']) {
                    File::ensureDirectoryExists($dest);
                    continue;
                }
                File::ensureDirectoryExists(dirname($dest));
                $stream = $zip->getStream((string) $zip->getNameIndex($entry['index']));
                if ($stream === false) {
                    throw new \RuntimeException("Cannot read archive entry '{$entry['rel']}'");
                }
                $out = fopen($dest, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
            }
            $zip->close();

            foreach ($entries as $entry) {
                if ($entry['dir']) {
                    File::ensureDirectoryExists("{$this->basePath}/{$entry['rel']}");
                    continue;
                }
                $targetPath = "{$this->basePath}/{$entry['rel']}";
                $backupPath = "{$backupDir}/{$entry['rel']}";
                if (file_exists($targetPath)) {
                    File::ensureDirectoryExists(dirname($backupPath));
                    File::copy($targetPath, $backupPath);
                }
                File::ensureDirectoryExists(dirname($targetPath));
                File::copy("{$extractDir}/{$entry['rel']}", $targetPath);
                $updatedFiles[] = $entry['rel'];
            }

            // Run migrations — a failure is a FAILED update, files restored.
            try {
                ($this->migrator)();
            } catch (\Throwable $e) {
                $this->restoreFiles($backupDir, $updatedFiles);
                Log::error('Update migration failed — files restored', ['error' => $e->getMessage()]);
                throw new \RuntimeException('Update migration failed: ' . $e->getMessage() . ' (previous files restored)', 0, $e);
            }
        } finally {
            File::deleteDirectory($extractDir);
        }

        $this->clearCaches();
        Cache::forget('cms_update_check');

        return [
            'previous_version' => $this->currentVersion,
            'files_updated' => count($updatedFiles),
            'backup_path' => $backupDir,
            'migrated' => true,
        ];
    }

    /**
     * Rollback to previous version from the most recent backup.
     */
    public function rollbackUpdate(): void
    {
        $backupDir = collect(File::directories($this->updateDir))
            ->filter(fn ($d) => str_starts_with(basename($d), 'backup-'))
            ->sortByDesc(fn ($d) => File::lastModified($d))
            ->first();

        if (!$backupDir) {
            throw new \RuntimeException('No backup found for rollback');
        }

        $this->restoreFiles($backupDir, null);
        $this->clearCaches();
        File::deleteDirectory($backupDir);
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    // ---- helpers ------------------------------------------------------------

    /** Copy files from a backup dir back into the base path (all, or only $updatedFiles). */
    private function restoreFiles(string $backupDir, ?array $updatedFiles): void
    {
        if ($updatedFiles !== null) {
            foreach ($updatedFiles as $rel) {
                $backup = "{$backupDir}/{$rel}";
                $target = "{$this->basePath}/{$rel}";
                if (is_file($backup)) {
                    File::copy($backup, $target);
                } else {
                    @unlink($target); // file did not exist before the update
                }
            }

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $rel = ltrim(substr($file->getPathname(), strlen($backupDir)), '/');
            if ($this->relativePathError($rel) !== null) {
                continue;
            }
            $targetPath = "{$this->basePath}/{$rel}";
            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($file->getPathname(), $targetPath);
        }
    }

    private function clearCaches(): void
    {
        foreach (['config:clear', 'route:clear', 'view:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (\Throwable $e) {
                Log::warning("Update: {$cmd} failed", ['error' => $e->getMessage()]);
            }
        }
    }

    /** Release archives usually wrap everything in one top-level folder. */
    private function detectSingleRootDir(ZipArchive $zip): ?string
    {
        $roots = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $first = explode('/', $name, 2)[0];
            if ($first === '' || !str_contains($name, '/')) {
                return null; // a top-level file → no wrapping folder
            }
            $roots[$first] = true;
            if (count($roots) > 1) {
                return null;
            }
        }
        $root = array_key_first($roots);
        if ($root === null) {
            return null;
        }
        // A package that only ships e.g. app/… is NOT wrapped — 'app' is an
        // application folder, not a release folder.
        foreach ((array) config('cms.updates.allowed_paths', []) as $allowed) {
            if (rtrim($allowed, '/') === $root) {
                return null;
            }
        }

        return $root . '/';
    }

    private function isSymlinkEntry(int $externalAttr): bool
    {
        return (($externalAttr >> 16) & 0170000) === 0120000;
    }

    /**
     * Null when the relative path is a safe, allow-listed application path;
     * otherwise the reason it is refused.
     */
    private function relativePathError(string $rel): ?string
    {
        if ($rel === '' || str_starts_with($rel, '/') || preg_match('/^[A-Za-z]:/', $rel)) {
            return 'absolute paths are not allowed';
        }
        if (str_contains($rel, '\\') || preg_match('/[\x00-\x1f\x7f]/', $rel)) {
            return 'invalid characters in path';
        }
        foreach (explode('/', $rel) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return 'path traversal is not allowed';
            }
        }
        foreach ((array) config('cms.updates.protected_paths', []) as $protected) {
            if ($rel === rtrim($protected, '/') || str_starts_with($rel, $protected)) {
                return "'{$protected}' is protected";
            }
        }
        foreach ((array) config('cms.updates.allowed_paths', []) as $allowed) {
            if ($rel === rtrim($allowed, '/') || str_starts_with($rel . '/', $allowed) || str_starts_with($rel, $allowed)) {
                return null;
            }
        }

        return 'path is outside the allowed release paths';
    }
}
