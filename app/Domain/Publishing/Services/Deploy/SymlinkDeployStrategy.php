<?php
namespace App\Domain\Publishing\Services\Deploy;

use App\Models\Deployment;
use Illuminate\Support\Facades\File;

class SymlinkDeployStrategy
{
    public function deploy(string $stagingPath, string $publicPath, Deployment $deployment): void
    {
        $parentDir = dirname($publicPath);
        File::ensureDirectoryExists($parentDir);

        $newLink = $publicPath . '.new';

        // Create new symlink
        if (is_link($newLink) || file_exists($newLink)) {
            @unlink($newLink);
        }
        symlink($stagingPath, $newLink);

        // Record previous target for rollback
        $previousTarget = is_link($publicPath) ? readlink($publicPath) : null;
        if ($previousTarget) {
            $deployment->update([
                'metadata' => array_merge($deployment->metadata ?? [], [
                    'previous_build' => $previousTarget,
                ]),
            ]);
        }

        // Atomic swap
        rename($newLink, $publicPath);

        $deployment->update(['artifact_path' => $stagingPath]);

        // Clean old builds
        $this->cleanOldBuilds($parentDir);
    }

    public function rollback(Deployment $deployment): void
    {
        $previousBuild = $deployment->metadata['previous_build'] ?? null;
        if (!$previousBuild || !is_dir($previousBuild)) {
            throw new \RuntimeException('No previous build found for rollback.');
        }

        $site = $deployment->site;
        $publicPath = config('publishing.public_path') . '/' . $site->slug;

        if (is_link($publicPath)) {
            unlink($publicPath);
        }
        symlink($previousBuild, $publicPath);
    }

    private function cleanOldBuilds(string $parentDir): void
    {
        // Delegates to the live-safe retention helper (FIX-B6a) so a publish
        // never deletes a build another site's live symlink still points to.
        \App\Domain\Publishing\Services\BuildRetention::prune(
            (int) config('publishing.max_retained_builds', 5) + 5
        );
    }
}
