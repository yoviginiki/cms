<?php

namespace App\Domain\Publishing\Services\Deploy;

use App\Models\Deployment;
use Illuminate\Support\Facades\Process;

class SshDeployStrategy
{
    /**
     * Deploy via rsync over SSH.
     */
    public function deploy(string $stagingPath, array $sshConfig, Deployment $deployment): void
    {
        $host = $sshConfig['host'] ?? '';
        $user = $sshConfig['user'] ?? '';
        $path = rtrim($sshConfig['path'] ?? '', '/') . '/';
        $port = $sshConfig['port'] ?? 22;
        $keyPath = $sshConfig['key_path'] ?? null;

        if (!$host || !$user || !$path) {
            throw new \RuntimeException('SSH deploy: host, user, and path are required.');
        }

        $sshOpts = "-o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p {$port}";
        if ($keyPath && file_exists($keyPath)) {
            $sshOpts .= " -i " . escapeshellarg($keyPath);
        }

        $source = rtrim($stagingPath, '/') . '/';
        $dest = escapeshellarg("{$user}@{$host}:{$path}");

        $cmd = sprintf(
            'rsync -azv --delete --chmod=D2775,F664 -e "ssh %s" %s %s',
            $sshOpts,
            escapeshellarg($source),
            $dest
        );

        $result = Process::timeout(120)->run($cmd);

        if (!$result->successful()) {
            throw new \RuntimeException('SSH deploy failed: ' . $result->errorOutput());
        }

        $deployment->update([
            'artifact_path' => $stagingPath,
            'metadata' => array_merge($deployment->metadata ?? [], [
                'deploy_method' => 'ssh',
                'deploy_host' => $host,
                'deploy_path' => $path,
                'rsync_output' => substr($result->output(), -500),
            ]),
        ]);
    }
}
