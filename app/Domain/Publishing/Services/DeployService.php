<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Services\Deploy\RenameDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SshDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SymlinkDeployStrategy;
use App\Models\Deployment;
use Illuminate\Support\Facades\File;

class DeployService
{
    public function deploy(Deployment $deployment, string $stagingPath): void
    {
        $site = $deployment->site;
        $settings = $site->settings ?? [];
        $method = $settings['deploy_method'] ?? 'local';

        match ($method) {
            'ssh' => $this->deploySsh($deployment, $stagingPath, $settings),
            'zip_only' => $this->deployZipOnly($deployment, $stagingPath),
            default => $this->deployLocal($deployment, $stagingPath),
        };
    }

    public function rollback(Deployment $deployment): void
    {
        $strategy = $this->resolveLocalStrategy();
        $strategy->rollback($deployment);
    }

    /**
     * Deploy locally (copy/symlink to public_path).
     *
     * For custom_domain sites: deploy to /home/cytechno/web/{domain}/public_html/
     * For slug-based sites: deploy to {public_path}/{slug}/
     */
    private function deployLocal(Deployment $deployment, string $stagingPath): void
    {
        $site = $deployment->site;

        if ($site->custom_domain) {
            // Deploy to the domain's own public_html directory
            $tenantBase = config('publishing.tenant_base', '/home/cytechno/web');
            // Sanitize domain — prevent path traversal
            $safeDomain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $site->custom_domain);
            if (!$safeDomain || str_contains($safeDomain, '..')) {
                throw new \RuntimeException("Invalid custom domain: {$site->custom_domain}");
            }
            $domainPath = $tenantBase . '/' . $safeDomain . '/public_html';

            if (!is_dir($domainPath)) {
                throw new \RuntimeException("Deploy target does not exist: {$domainPath}. Create the domain in Hestia first.");
            }

            $this->copyDeploy($stagingPath, $domainPath, $deployment);
        } else {
            $basePath = config('publishing.public_path');
            $publicPath = $basePath . '/' . $site->slug;
            $strategy = $this->resolveLocalStrategy();
            $strategy->deploy($stagingPath, $publicPath, $deployment);
        }
    }

    /**
     * Deploy via rsync over SSH.
     */
    private function deploySsh(Deployment $deployment, string $stagingPath, array $settings): void
    {
        $sshConfig = [
            'host' => $settings['deploy_ssh_host'] ?? '',
            'user' => $settings['deploy_ssh_user'] ?? '',
            'path' => $settings['deploy_ssh_path'] ?? '',
            'port' => $settings['deploy_ssh_port'] ?? 22,
            'key_path' => $settings['deploy_ssh_key'] ?? null,
        ];

        $strategy = new SshDeployStrategy();
        $strategy->deploy($stagingPath, $sshConfig, $deployment);
    }

    /**
     * ZIP-only: just keep the build, no deploy. Users download the ZIP manually.
     */
    private function deployZipOnly(Deployment $deployment, string $stagingPath): void
    {
        $deployment->update([
            'artifact_path' => $stagingPath,
            'metadata' => array_merge($deployment->metadata ?? [], [
                'deploy_method' => 'zip_only',
                'zip_ready' => true,
            ]),
        ]);
    }

    /**
     * Direct copy deploy for custom domain sites.
     */
    private function copyDeploy(string $stagingPath, string $targetPath, Deployment $deployment): void
    {
        File::ensureDirectoryExists($targetPath);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stagingPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace($stagingPath . '/', '', $item->getPathname());
            $dest = $targetPath . '/' . $relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($dest);
                @chmod($dest, 0775);
            } else {
                if (file_exists($dest) && !is_writable($dest)) {
                    @chmod($dest, 0664);
                }
                File::copy($item->getPathname(), $dest);
                @chmod($dest, 0664);
            }
        }

        $deployment->update(['artifact_path' => $stagingPath]);
    }

    private function resolveLocalStrategy(): SymlinkDeployStrategy|RenameDeployStrategy
    {
        $configured = config('publishing.deploy_strategy');

        if ($configured === 'symlink') return new SymlinkDeployStrategy();
        if ($configured === 'rename') return new RenameDeployStrategy();

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
