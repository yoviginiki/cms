<?php

namespace App\Domain\System\Services;

use ZipArchive;

/**
 * Packs the WHOLE CMS as source into storage/app/cms-export.zip — everything
 * under the project root except what is generated or secret:
 *
 *  - vendor/ and node_modules/ (any depth): restored by composer/npm install
 *  - .git/, storage/, .phpunit.result.cache: history, runtime data, caches
 *  - .env (any depth; .env.example files are kept)
 *  - public/admin-assets/assets/*: only the chunks the CURRENT .vite manifest
 *    references (vite keeps stale hashed chunks around for open tabs — up to
 *    thousands of files; they were ~80% of the old export)
 *
 * Used by the admin sidebar "Generate/Download" (SystemController) and by
 * `php artisan cms:export`. Builds to a temp file and renames atomically.
 */
class CmsExportService
{
    public const ZIP = 'app/cms-export.zip';

    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', 'storage'];
    private const SKIP_FILES = ['.env', '.phpunit.result.cache', '.DS_Store'];

    /** @return array{path:string,files:int,bytes:int,skipped_chunks:int} */
    public function build(?string $zipPath = null, ?string $basePath = null): array
    {
        $base = rtrim($basePath ?? base_path(), '/');
        $zipPath ??= storage_path(self::ZIP);
        $tmp = dirname($zipPath) . '/tmp/cms-export-build.zip';
        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0775, true);
        }

        $keepChunks = $this->manifestFiles($base);

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create export zip at {$tmp}");
        }

        $files = 0;
        $skippedChunks = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $f) use ($base) {
                    $name = $f->getFilename();
                    if ($f->isDir()) {
                        return ! in_array($name, self::SKIP_DIRS, true);
                    }
                    if (in_array($name, self::SKIP_FILES, true)) {
                        return false;
                    }
                    // the export itself (and its temp) must not nest into the next export
                    return ! str_starts_with($f->getPathname(), $base . '/storage/');
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $rel = substr($file->getPathname(), strlen($base) + 1);
            if (str_starts_with($rel, 'public/admin-assets/assets/') && ! isset($keepChunks[$rel])) {
                $skippedChunks++;
                continue;
            }
            $zip->addFile($file->getPathname(), $rel);
            $files++;
        }
        $zip->close();

        rename($tmp, $zipPath);

        return ['path' => $zipPath, 'files' => $files, 'bytes' => (int) filesize($zipPath), 'skipped_chunks' => $skippedChunks];
    }

    /** Paths (relative to base) of every file the current admin build references. */
    private function manifestFiles(string $base): array
    {
        $manifest = $base . '/public/admin-assets/.vite/manifest.json';
        if (! is_file($manifest)) {
            return [];
        }
        $m = json_decode((string) file_get_contents($manifest), true) ?: [];
        $keep = [];
        foreach ($m as $entry) {
            foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? [], $entry['assets'] ?? []) as $f) {
                if (is_string($f) && $f !== '') {
                    $keep['public/admin-assets/' . $f] = true;
                }
            }
        }

        return $keep;
    }
}
