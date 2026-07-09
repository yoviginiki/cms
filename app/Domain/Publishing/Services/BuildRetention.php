<?php

namespace App\Domain\Publishing\Services;

use Illuminate\Support\Facades\File;

/**
 * Audit FIX-B6a — prune old build directories WITHOUT ever deleting a build
 * that a live site symlink still points to.
 *
 * The live docroot for a slug site is a symlink into storage/app/builds/{id}.
 * The previous pruning globbed that global dir and deleted by mtime, so once
 * there were more than N builds across ALL sites, older sites' *live* builds
 * were deleted — leaving dangling symlinks (dead sites). This prunes the same
 * dir but first collects every live symlink target and refuses to delete them.
 */
class BuildRetention
{
    public static function prune(int $keep = 10): void
    {
        $buildRoot = config('publishing.staging_path');
        if (!$buildRoot || !is_dir($buildRoot)) {
            return;
        }

        $live = self::liveBuildTargets();

        $dirs = collect(File::directories($buildRoot))
            ->sortByDesc(fn ($d) => File::lastModified($d))
            ->values();

        foreach ($dirs->slice($keep) as $old) {
            $real = realpath($old) ?: $old;
            if (in_array($real, $live, true)) {
                continue; // never delete a build a live site is serving
            }
            File::deleteDirectory($old);
        }
    }

    /** Absolute targets of every live site symlink under the public path. */
    public static function liveBuildTargets(): array
    {
        $publicPath = config('publishing.public_path');
        $targets = [];
        if (!$publicPath || !is_dir($publicPath)) {
            return $targets;
        }

        foreach (scandir($publicPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $publicPath . '/' . $entry;
            if (is_link($path)) {
                $t = readlink($path);
                if ($t !== false) {
                    $targets[] = realpath($t) ?: $t;
                }
            }
        }

        return $targets;
    }
}
