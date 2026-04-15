<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Services\Deploy\RenameDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SymlinkDeployStrategy;
use App\Models\Deployment;

class DeployService
{
    public function deploy(Deployment $deployment, string $stagingPath): void
    {
        $site = $deployment->site;
        $publicPath = config('publishing.public_path') . '/' . $site->slug;
        $strategy = $this->resolveStrategy();
        $strategy->deploy($stagingPath, $publicPath, $deployment);
    }

    public function rollback(Deployment $deployment): void
    {
        $strategy = $this->resolveStrategy();
        $strategy->rollback($deployment);
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
