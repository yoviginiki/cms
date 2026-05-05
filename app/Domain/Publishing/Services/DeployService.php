<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Services\Deploy\RenameDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SymlinkDeployStrategy;
use App\Models\Deployment;
use Illuminate\Support\Facades\File;

class DeployService
{
    public function deploy(Deployment $deployment, string $stagingPath): void
    {
        $site = $deployment->site;
        $basePath = config('publishing.public_path');

        if ($site->custom_domain) {
            // Custom domain: copy files directly to the public path root
            $this->copyDeploy($stagingPath, $basePath, $deployment);
        } else {
            // Subdomain: symlink/rename into a subdirectory
            $publicPath = $basePath . '/' . $site->slug;
            $strategy = $this->resolveStrategy();
            $strategy->deploy($stagingPath, $publicPath, $deployment);
        }
    }

    public function rollback(Deployment $deployment): void
    {
        $strategy = $this->resolveStrategy();
        $strategy->rollback($deployment);
    }

    /**
     * Direct copy deploy for custom domain sites.
     * Copies built files into the document root, preserving non-CMS files.
     */
    private function copyDeploy(string $stagingPath, string $targetPath, Deployment $deployment): void
    {
        File::ensureDirectoryExists($targetPath);

        // Copy all built files to target
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stagingPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace($stagingPath . '/', '', $item->getPathname());
            $dest = $targetPath . '/' . $relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($dest);
                // Ensure writable by web server group
                @chmod($dest, 0775);
            } else {
                // If target exists but isn't writable, fix permissions first
                if (file_exists($dest) && !is_writable($dest)) {
                    @chmod($dest, 0664);
                }
                File::copy($item->getPathname(), $dest);
                @chmod($dest, 0664);
            }
        }

        $deployment->update(['artifact_path' => $stagingPath]);
    }

    private function resolveStrategy(): SymlinkDeployStrategy|RenameDeployStrategy
    {
        $configured = config('publishing.deploy_strategy');

        if ($configured === 'symlink') return new SymlinkDeployStrategy();
        if ($configured === 'rename') return new RenameDeployStrategy();

        // Auto-detect
        $testDir = config('publishing.public_path');
        if (!is_dir($testDir)) @mkdir($testDir, 0755, true);

        $testLink = $testDir . '/.symlink_test_' . uniqid();
        $testTarget = $testDir;

        if (@symlink($testTarget, $testLink)) {
            @unlink($testLink);
            return new SymlinkDeployStrategy();
        }

        return new RenameDeployStrategy();
    }
}
