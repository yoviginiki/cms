<?php

namespace App\Domain\System\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;

class UpdateService
{
    private string $updateServer;
    private string $currentVersion;

    public function __construct()
    {
        $this->updateServer = config('cms.updates.server', 'https://updates.ensodo.eu');
        $this->currentVersion = config('cms.version', '1.0.0');
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

    /**
     * Download an update package.
     */
    public function downloadUpdate(string $version, string $downloadUrl, string $expectedChecksum): string
    {
        $updateDir = storage_path('app/updates');
        File::ensureDirectoryExists($updateDir);

        $zipPath = "{$updateDir}/cms-{$version}.zip";

        $response = Http::timeout(120)->withOptions(['sink' => $zipPath])->get($downloadUrl);
        if (!$response->successful()) {
            throw new \RuntimeException("Failed to download update (HTTP {$response->status()})");
        }

        // Verify checksum
        $actualChecksum = 'sha256:' . hash_file('sha256', $zipPath);
        if ($actualChecksum !== $expectedChecksum) {
            File::delete($zipPath);
            throw new \RuntimeException("Checksum mismatch! Expected {$expectedChecksum}, got {$actualChecksum}");
        }

        return $zipPath;
    }

    /**
     * Apply an update from a downloaded ZIP.
     */
    public function applyUpdate(string $zipPath): array
    {
        $basePath = base_path();
        $updateDir = storage_path('app/updates');
        $extractDir = "{$updateDir}/extracted";
        $backupDir = "{$updateDir}/backup-{$this->currentVersion}";

        // Extract
        File::ensureDirectoryExists($extractDir);
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open update archive');
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // Find the root dir inside the zip
        $dirs = File::directories($extractDir);
        $sourceDir = count($dirs) === 1 ? $dirs[0] : $extractDir;

        // Backup current files that will be overwritten
        File::ensureDirectoryExists($backupDir);
        $updatedFiles = [];

        $protectedPaths = ['.env', 'storage', 'public/admin-assets'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = str_replace($sourceDir . '/', '', $file->getPathname());

            // Skip protected paths
            $skip = false;
            foreach ($protectedPaths as $pp) {
                if (str_starts_with($relativePath, $pp)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $targetPath = "{$basePath}/{$relativePath}";
            $backupPath = "{$backupDir}/{$relativePath}";

            if ($file->isDir()) {
                File::ensureDirectoryExists($targetPath);
                File::ensureDirectoryExists($backupPath);
            } else {
                // Backup existing file
                if (file_exists($targetPath)) {
                    File::ensureDirectoryExists(dirname($backupPath));
                    File::copy($targetPath, $backupPath);
                }

                // Copy new file
                File::ensureDirectoryExists(dirname($targetPath));
                File::copy($file->getPathname(), $targetPath);
                $updatedFiles[] = $relativePath;
            }
        }

        // Run migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Update migration failed', ['error' => $e->getMessage()]);
        }

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Cleanup extracted files
        File::deleteDirectory($extractDir);

        // Clear update check cache
        Cache::forget('cms_update_check');

        return [
            'previous_version' => $this->currentVersion,
            'files_updated' => count($updatedFiles),
            'backup_path' => $backupDir,
        ];
    }

    /**
     * Rollback to previous version from backup.
     */
    public function rollbackUpdate(): void
    {
        $updateDir = storage_path('app/updates');
        $backupDirs = File::directories($updateDir);

        // Find latest backup
        $backupDir = collect($backupDirs)
            ->filter(fn($d) => str_contains(basename($d), 'backup-'))
            ->sortByDesc(fn($d) => File::lastModified($d))
            ->first();

        if (!$backupDir) {
            throw new \RuntimeException('No backup found for rollback');
        }

        $basePath = base_path();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            $relativePath = str_replace($backupDir . '/', '', $file->getPathname());
            $targetPath = "{$basePath}/{$relativePath}";

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($file->getPathname(), $targetPath);
        }

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Remove backup
        File::deleteDirectory($backupDir);
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }
}
