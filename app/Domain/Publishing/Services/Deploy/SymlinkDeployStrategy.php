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

        // A legacy docroot deployed by the copy/rename strategy is a REAL
        // directory — rename() of a symlink over a non-empty directory always
        // fails (EISDIR). Move the legacy tree aside into the rollback area
        // (preserved, never deleted) so the symlink can take its place.
        if (is_dir($publicPath) && !is_link($publicPath)) {
            $legacyKeep = rtrim((string) config('publishing.rollback_path'), '/') . "/{$deployment->id}-legacy-docroot";
            File::ensureDirectoryExists(dirname($legacyKeep));
            rename($publicPath, $legacyKeep);
            $deployment->update([
                'metadata' => array_merge($deployment->metadata ?? [], [
                    'legacy_docroot_moved_to' => $legacyKeep,
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
        $publicPath = config('publishing.public_path') . '/' . $site->deploySlug();

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
