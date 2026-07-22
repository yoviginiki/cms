<?php

namespace App\Services\SiteWizard;

use App\Models\SiteWizard\SiteWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Safe extraction of an uploaded design ZIP (e.g. a Canva website export) into
 * the session workspace, and discovery of its HTML pages.
 *
 * The archive is untrusted input, so extraction is entry-by-entry — NEVER a
 * blind extractTo(): zip-slip names are rejected, symlinks skipped, only
 * allowlisted extensions land on disk, and per-file/total uncompressed sizes
 * are capped BEFORE any bytes are written (zip-bomb guard).
 */
class ZipSiteIngestor
{
    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'json', 'txt', 'xml', 'ico', 'svg',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif',
        'woff', 'woff2', 'ttf', 'otf', 'mp4', 'webm',
    ];

    /** Store the upload into the session workspace; returns the absolute zip path. */
    public function store(SiteWizardSession $session, UploadedFile $zip): string
    {
        $workspace = $this->workspacePath($session);
        File::ensureDirectoryExists($workspace);
        $zip->move($workspace, 'upload.zip');

        $session->update(['workspace_path' => "site-wizard/{$session->id}"]);

        return "{$workspace}/upload.zip";
    }

    /**
     * Extract + discover pages.
     *
     * @return array{root: string, pages: array<int, array{path: string, slug: string, is_home: bool}>, stats: array}
     */
    public function extract(SiteWizardSession $session): array
    {
        $workspace = $this->workspacePath($session);
        $zipPath = "{$workspace}/upload.zip";
        if (!is_file($zipPath)) {
            throw new RuntimeException('The uploaded file is missing — start the wizard again.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('That file could not be opened as a ZIP archive.');
        }

        $maxFiles = (int) config('cms.site_wizard.zip_max_files', 5000);
        $maxFileBytes = 10 * 1024 * 1024;
        $maxTotalBytes = (int) config('cms.site_wizard.zip_max_uncompressed_mb', 250) * 1024 * 1024;

        if ($zip->numFiles > $maxFiles) {
            $zip->close();
            throw new RuntimeException("The archive has too many files (max {$maxFiles}).");
        }

        // Pass 1 — total uncompressed size BEFORE writing anything.
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $total += (int) ($zip->statIndex($i)['size'] ?? 0);
        }
        if ($total > $maxTotalBytes) {
            $zip->close();
            throw new RuntimeException('The archive is too large when uncompressed.');
        }

        $root = "{$workspace}/files";
        File::ensureDirectoryExists($root);

        // Pass 2 — entry-by-entry sanitized extraction.
        $stats = ['files' => 0, 'skipped_ext' => [], 'by_ext' => []];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');

            if ($name === '' || str_ends_with($name, '/')) {
                continue; // directory entry
            }
            if (!$this->safeEntryName($name)) {
                $zip->close();
                throw new RuntimeException('The archive contains an unsafe file path.');
            }
            // Symlink entries could point extraction outside the workspace.
            $attrs = $zip->getExternalAttributesIndex($i, $opsys, $attr) ? $attr : 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX
                && ((($attr >> 16) & 0xF000) === 0xA000)) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $stats['skipped_ext'][$ext] = ($stats['skipped_ext'][$ext] ?? 0) + 1;
                continue;
            }
            if ((int) ($stat['size'] ?? 0) > $maxFileBytes) {
                continue;
            }
            $stats['files']++;
            $stats['by_ext'][$ext] = ($stats['by_ext'][$ext] ?? 0) + 1;

            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $target = $root . '/' . $name;
            File::ensureDirectoryExists(dirname($target));
            file_put_contents($target, stream_get_contents($stream, $maxFileBytes));
            fclose($stream);
        }
        $zip->close();

        // Canva-style single-root-folder archives: unwrap so index.html sits at the root.
        $entries = array_values(array_diff(scandir($root) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir("{$root}/{$entries[0]}")) {
            $root = "{$root}/{$entries[0]}";
        }

        $pages = $this->discoverPages($root);
        if ($pages === []) {
            $seen = $stats['by_ext'] === [] ? 'nothing usable' : implode(', ', array_map(
                fn ($ext, $n) => "{$n} {$ext}", array_keys($stats['by_ext']), $stats['by_ext'],
            ));
            throw new RuntimeException("No HTML pages were found in the archive (it contains {$seen}) — export your design as HTML and try again.");
        }

        return ['root' => $root, 'pages' => $pages, 'stats' => $stats];
    }

    /** Extracted files root, re-applying the single-root-folder unwrap deterministically. */
    public function filesRoot(SiteWizardSession $session): string
    {
        $root = $this->workspacePath($session) . '/files';
        $entries = array_values(array_diff(scandir($root) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir("{$root}/{$entries[0]}")) {
            $root = "{$root}/{$entries[0]}";
        }

        return $root;
    }

    public function cleanup(SiteWizardSession $session): void
    {
        if ($session->workspace_path) {
            File::deleteDirectory(storage_path("app/{$session->workspace_path}"));
        }
    }

    public function workspacePath(SiteWizardSession $session): string
    {
        return storage_path("app/site-wizard/{$session->id}");
    }

    /** Reject absolute paths, drive letters, traversal segments, and control chars. */
    private function safeEntryName(string $name): bool
    {
        if (str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, "\0")) {
            return false;
        }
        if (preg_match('/^[a-zA-Z]:[\/\\\\]/', $name) || preg_match('/[\x00-\x1f]/', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Find the site's pages: *.html up to 5 levels deep, shallow first; the
     * shallowest index.html is the homepage. Capped at 100 — language trees
     * (en/…, bg/…) count as normal pages.
     *
     * @return array<int, array{path: string, slug: string, is_home: bool}>
     */
    private function discoverPages(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['html', 'htm'], true)) {
                continue;
            }
            $rel = ltrim(substr($file->getPathname(), strlen($root)), '/');
            $depth = substr_count($rel, '/');
            if ($depth > 5) {
                continue;
            }
            $files[] = ['path' => $rel, 'depth' => $depth];
        }

        usort($files, fn ($a, $b) => [$a['depth'], $a['path']] <=> [$b['depth'], $b['path']]);
        $files = array_slice($files, 0, 100);

        $homeIndex = null;
        foreach ($files as $i => $f) {
            if (strtolower(basename($f['path'])) === 'index.html') {
                $homeIndex = $i;
                break; // files are shallow-first, so this is the shallowest index.html
            }
        }
        $homeIndex ??= 0;

        $seen = [];
        $pages = [];
        foreach ($files as $i => $f) {
            $isHome = $i === $homeIndex;
            $slug = $isHome ? 'home' : $this->slugFromPath($f['path']);
            $base = $slug;
            $n = 2;
            while (in_array($slug, $seen, true)) {
                $slug = "{$base}-{$n}";
                $n++;
            }
            $seen[] = $slug;
            $pages[] = ['path' => $f['path'], 'slug' => $slug, 'is_home' => $isHome];
        }

        return $pages;
    }

    private function slugFromPath(string $relativePath): string
    {
        $noExt = preg_replace('/\.html?$/i', '', $relativePath);
        if (strtolower(basename($noExt)) === 'index') {
            $noExt = dirname($noExt);
        }
        $slug = \Illuminate\Support\Str::slug(str_replace('/', '-', $noExt));

        return $slug !== '' ? $slug : 'page';
    }
}
