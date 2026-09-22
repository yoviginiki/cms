<?php

namespace App\Domain\Publishing\Services;

use App\Models\Deployment;
use Illuminate\Support\Facades\File;

/**
 * Prune old build directories without ever deleting one that is still
 * needed (FIX-B6a, hardened for F18 audit 2026-09-22).
 *
 * A build dir is protected when it is:
 *  - the target of a live site symlink under the public path;
 *  - the build of a deployment that is active (queued/building/deploying)
 *    or parked at `staged` (awaiting promotion);
 *  - the artifact of the newest LIVE deployment of any site, or the build
 *    that deployment recorded as `previous_build` (the rollback target);
 *  - referenced by `rollback_to` / `rolled_back_to` of a recent rollback.
 * The remaining (terminal, unreferenced) dirs are kept per site up to
 * `keep`, newest first; older ones are deleted. Dirs that belong to no known
 * deployment are pruned by age only when older than the newest kept build.
 */
class BuildRetention
{
    public static function prune(int $keep = 10): void
    {
        $buildRoot = config('publishing.staging_path');
        if (!$buildRoot || !is_dir($buildRoot)) {
            return;
        }
        $realRoot = realpath($buildRoot) ?: $buildRoot;

        $protected = array_flip(array_map(fn ($p) => realpath($p) ?: $p, self::protectedPaths()));

        $bySite = [];   // site_id => [[path, mtime], ...]
        $orphans = [];
        foreach (File::directories($buildRoot) as $dir) {
            $real = realpath($dir) ?: $dir;
            if (isset($protected[$real])) {
                continue;
            }
            $siteId = self::siteIdFor(basename($dir));
            $entry = ['path' => $dir, 'mtime' => File::lastModified($dir)];
            if ($siteId === null) {
                $orphans[] = $entry;
            } else {
                $bySite[$siteId][] = $entry;
            }
        }

        foreach ($bySite as $entries) {
            usort($entries, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
            foreach (array_slice($entries, $keep) as $old) {
                self::safeDelete($old['path'], $realRoot);
            }
        }
        usort($orphans, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        foreach (array_slice($orphans, $keep) as $old) {
            self::safeDelete($old['path'], $realRoot);
        }
    }

    /** Every build path something still depends on. */
    public static function protectedPaths(): array
    {
        $root = rtrim((string) config('publishing.staging_path'), '/');
        $paths = self::liveBuildTargets();

        $rows = Deployment::query()
            ->whereIn('status', ['queued', 'building', 'deploying', 'staged'])
            ->get(['id', 'artifact_path', 'metadata']);
        foreach ($rows as $d) {
            $paths[] = "{$root}/{$d->id}";
            $paths[] = "{$root}/{$d->id}-release";
            if ($d->artifact_path) {
                $paths[] = $d->artifact_path;
            }
        }

        // Newest live deployment per site + its rollback target.
        $liveIds = Deployment::query()
            ->whereIn('status', ['live', 'rolled_back'])
            ->selectRaw('DISTINCT ON (site_id) id')
            ->orderByRaw('site_id, completed_at DESC NULLS LAST, created_at DESC')
            ->pluck('id');
        foreach (Deployment::whereIn('id', $liveIds)->get(['id', 'artifact_path', 'metadata']) as $d) {
            $paths[] = "{$root}/{$d->id}";
            $paths[] = "{$root}/{$d->id}-release";
            if ($d->artifact_path) {
                $paths[] = $d->artifact_path;
            }
            foreach (['previous_build', 'rolled_back_to', 'rollback_to'] as $k) {
                $v = $d->metadata[$k] ?? null;
                if (is_string($v) && $v !== '') {
                    $paths[] = str_contains($v, '/') ? $v : "{$root}/{$v}";
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
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

    /** Owning site of a build dir (dir name = deployment id, optionally "-release"). */
    private static function siteIdFor(string $dirName): ?string
    {
        $id = preg_replace('/-release$/', '', $dirName);
        if (!\Illuminate\Support\Str::isUuid($id)) {
            return null;
        }
        $siteId = Deployment::whereKey($id)->value('site_id');

        return $siteId === null ? null : (string) $siteId;
    }

    private static function safeDelete(string $dir, string $realRoot): void
    {
        $real = realpath($dir);
        if (!$real || !str_starts_with($real, $realRoot . '/') || is_link($dir)) {
            return;
        }
        File::deleteDirectory($dir);
    }
}
