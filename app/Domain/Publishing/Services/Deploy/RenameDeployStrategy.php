<?php
namespace App\Domain\Publishing\Services\Deploy;

use App\Models\Deployment;
use Illuminate\Support\Facades\File;

class RenameDeployStrategy
{
    public function deploy(string $stagingPath, string $publicPath, Deployment $deployment): void
    {
        File::ensureDirectoryExists($publicPath);
        $rollbackPath = config('publishing.rollback_path') . '/' . $deployment->id;
        File::ensureDirectoryExists($rollbackPath);

        // Generate manifest of new files
        $newFiles = $this->getFileList($stagingPath);

        // Backup existing files that will be changed
        foreach ($newFiles as $relativePath) {
            $livePath = $publicPath . '/' . $relativePath;
            if (file_exists($livePath)) {
                $backupDest = $rollbackPath . '/' . $relativePath;
                File::ensureDirectoryExists(dirname($backupDest));
                copy($livePath, $backupDest);
            }
        }

        // Deploy files atomically (per-file rename)
        // Order: assets first, then HTML, then sitemap
        $htmlFiles = [];
        $otherFiles = [];
        foreach ($newFiles as $relativePath) {
            if (str_ends_with($relativePath, '.html')) {
                $htmlFiles[] = $relativePath;
            } else {
                $otherFiles[] = $relativePath;
            }
        }

        foreach (array_merge($otherFiles, $htmlFiles) as $relativePath) {
            $sourcePath = $stagingPath . '/' . $relativePath;
            $destPath = $publicPath . '/' . $relativePath;
            $tmpPath = $destPath . '.tmp';

            File::ensureDirectoryExists(dirname($destPath));
            copy($sourcePath, $tmpPath);
            rename($tmpPath, $destPath);
        }

        $deployment->update(['artifact_path' => $publicPath]);
    }

    public function rollback(Deployment $deployment): void
    {
        $rollbackPath = config('publishing.rollback_path') . '/' . $deployment->id;
        if (!is_dir($rollbackPath)) {
            throw new \RuntimeException('No rollback data found.');
        }

        $site = $deployment->site;
        $publicPath = config('publishing.public_path') . '/' . $site->deploySlug();
        $files = $this->getFileList($rollbackPath);

        foreach ($files as $relativePath) {
            $source = $rollbackPath . '/' . $relativePath;
            $dest = $publicPath . '/' . $relativePath;
            File::ensureDirectoryExists(dirname($dest));
            copy($source, $dest);
        }
    }

    private function getFileList(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $files[] = ltrim(str_replace($directory, '', $file->getPathname()), '/');
        }

        return $files;
    }
}
