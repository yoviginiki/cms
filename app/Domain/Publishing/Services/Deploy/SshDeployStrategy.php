<?php

namespace App\Domain\Publishing\Services\Deploy;

use App\Models\Deployment;
use Illuminate\Support\Facades\Process;

class SshDeployStrategy
{
    /**
     * Deploy via rsync over SSH (H01, audit 2026-09-22): every field is
     * validated by SshTarget and the command runs as an argument vector — no
     * shell, so no value can change how the command is interpreted.
     */
    public function deploy(string $stagingPath, array $settings, Deployment $deployment): void
    {
        try {
            $target = SshTarget::fromSettings($settings);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('SSH deploy refused: ' . $e->getMessage(), 0, $e);
        }

        $result = Process::timeout(120)->run($target->rsyncCommand($stagingPath));

        if (!$result->successful()) {
            throw new \RuntimeException('SSH deploy failed: ' . $result->errorOutput());
        }

        $deployment->update([
            'artifact_path' => $stagingPath,
            'metadata' => array_merge($deployment->metadata ?? [], [
                'deploy_method' => 'ssh',
                'deploy_host' => $target->host,
                'deploy_path' => $target->path,
                'rsync_output' => substr($result->output(), -500),
            ]),
        ]);
    }
}
