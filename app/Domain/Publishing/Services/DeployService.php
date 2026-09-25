<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Services\Deploy\RenameDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SshDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SymlinkDeployStrategy;
use App\Models\Deployment;
use Illuminate\Support\Facades\File;

class DeployService
{
    public function __construct(private DeployTargetResolver $targets = new DeployTargetResolver())
    {
    }

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

    /**
     * Deploy a PARTIAL build (stale-batch): per-file merge into the live
     * docroot. Never uses the symlink strategy — swapping the whole docroot
     * for a build that contains only a few pages would delete the rest of
     * the site. SSH deploys use rsync --delete (same hazard) and zip_only has
     * no live target, so both are rejected — those sites use full publish.
     */
    public function deployPartial(Deployment $deployment, string $stagingPath): void
    {
        $site = $deployment->site;
        $method = ($site->settings ?? [])['deploy_method'] ?? 'local';

        if ($method !== 'local') {
            throw new \RuntimeException("Partial deploys are not supported for the '{$method}' deploy method — run a full publish instead.");
        }

        if ($site->custom_domain) {
            // Reserved-domain, containment and ownership checks live in ONE
            // place (F03) — the FormRequest is not the only way a domain
            // reaches the deploy layer.
            $targetPath = $this->targets->authorizeCustomDomainTarget($site);
        } else {
            $targetPath = $this->targets->slugDocroot($site);
            // F18 — immutable releases. A symlinked docroot points at a FULL
            // build; writing into it would mutate that historical artifact
            // (a later rollback to it would return content it never had).
            // Instead: hard-link copy of the live release → merge the delta
            // into the copy → atomic symlink swap. The old release keeps its
            // inodes untouched (per-file tmp+rename replaces directory
            // entries, never file contents).
            if (is_link($targetPath)) {
                $this->deployPartialAsRelease($deployment, $stagingPath, $targetPath);

                return;
            }
        }

        // MERGE ONLY — a partial staging tree holds a few pages; pruning
        // against it would delete the rest of the live site. Stale files from
        // slug renames are StalePathCleaner's job, not the deploy's.
        $this->copyDeploy($stagingPath, $targetPath, $deployment, prune: false);
    }

    /** Delta onto a symlink-served site: new full release view + atomic swap. */
    private function deployPartialAsRelease(Deployment $deployment, string $stagingPath, string $publicPath): void
    {
        $current = readlink($publicPath);
        if ($current === false || !is_dir($current)) {
            throw new \RuntimeException("Live symlink {$publicPath} has no valid target; run a full publish.");
        }
        $release = rtrim((string) config('publishing.staging_path'), '/') . "/{$deployment->id}-release";
        if (is_dir($release)) {
            File::deleteDirectory($release); // a previous attempt of this same deployment
        }
        self::linkTree($current, $release);
        $this->copyDeploy($stagingPath, $release, $deployment, prune: false);

        // Atomic swap (+ previous_build for rollback, retention).
        (new SymlinkDeployStrategy())->deploy($release, $publicPath, $deployment);
    }

    /**
     * Replicate $src into $dst using hard links for files (same filesystem),
     * falling back to copies; directories are created, symlinks re-created.
     */
    public static function linkTree(string $src, string $dst): void
    {
        File::ensureDirectoryExists($dst);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $rel = ltrim(substr($item->getPathname(), strlen($src)), '/');
            $target = "{$dst}/{$rel}";
            if ($item->isLink()) {
                @symlink((string) readlink($item->getPathname()), $target);
            } elseif ($item->isDir()) {
                File::ensureDirectoryExists($target);
            } else {
                File::ensureDirectoryExists(dirname($target));
                if (!@link($item->getPathname(), $target)) {
                    File::copy($item->getPathname(), $target);
                }
            }
        }
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
            // Deploy to the domain's own public_html directory. The target
            // must be provisioned (exists under tenant_base), not reserved,
            // and unclaimed or claimed by THIS site (F03).
            $domainPath = $this->targets->authorizeCustomDomainTarget($site);

            $this->copyDeploy($stagingPath, $domainPath, $deployment);
        } else {
            $publicPath = $this->targets->slugDocroot($site);
            $strategy = $this->resolveLocalStrategy();
            $strategy->deploy($stagingPath, $publicPath, $deployment);

            // A changed deploy folder (settings.deploy_slug) must not leave the
            // previous folder serving stale content — remove any OTHER symlink
            // that still points at one of this site's builds.
            $this->removeStaleDeployLinks($site);
        }
    }

    /**
     * Unlink every symlink in the public path that (a) is not this site's
     * current deploy folder and (b) targets a build belonging to this site.
     * Only symlinks are ever touched — real directories are never deleted.
     */
    private function removeStaleDeployLinks(\App\Models\Site $site): void
    {
        $publicPath = config('publishing.public_path');
        $current = $site->deploySlug();
        if (!is_dir($publicPath)) {
            return;
        }

        foreach (scandir($publicPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === $current) {
                continue;
            }
            $link = $publicPath . '/' . $entry;
            if (!is_link($link)) {
                continue;
            }
            if ($this->targets->linkOwner($link) === (string) $site->id) {
                unlink($link);
            }
        }
    }

    /**
     * Deploy via rsync over SSH.
     */
    private function deploySsh(Deployment $deployment, string $stagingPath, array $settings): void
    {
        (new SshDeployStrategy())->deploy($stagingPath, $settings, $deployment);
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
    private function copyDeploy(string $stagingPath, string $targetPath, Deployment $deployment, bool $prune = true): void
    {
        // A pruning deploy must never target a build directory: those are the
        // live content of symlink-served sites. Before the F01 symlink guard,
        // ensodo.eu's full publish pruned into them and emptied vioiv,
        // heikotera-com, men-root and docs (Aug 2026) — keep this a hard stop.
        if ($prune && $this->isInsideBuilds($targetPath)) {
            throw new \RuntimeException("Refusing to prune-deploy into a build directory: {$targetPath}");
        }

        File::ensureDirectoryExists($targetPath);

        // Copy new content and record every relative path the build defines.
        // Ordering (§7 atomicity): directories and assets land BEFORE any
        // .html file, so a page can never go live referencing a hashed asset
        // that hasn't been copied yet. PHP's sort is stable, so the parent-
        // before-child order from SELF_FIRST is preserved inside each group.
        $keep = [];
        $items = iterator_to_array(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stagingPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        ), false);
        usort($items, fn ($a, $b) => (int) (!$a->isDir() && str_ends_with($a->getPathname(), '.html'))
            <=> (int) (!$b->isDir() && str_ends_with($b->getPathname(), '.html')));

        foreach ($items as $item) {
            $relative = str_replace($stagingPath . '/', '', $item->getPathname());
            $keep[$relative] = true;
            $dest = $targetPath . '/' . $relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($dest);
                @chmod($dest, 0775);
            } else {
                if (file_exists($dest) && !is_writable($dest)) {
                    @chmod($dest, 0664);
                }
                // Atomic per-file swap: write beside the target, then rename()
                // (atomic on the same filesystem) — a visitor mid-deploy sees
                // the old file or the new one, never a torn half-write.
                $tmp = $dest . '.tmp-' . getmypid();
                File::copy($item->getPathname(), $tmp);
                @chmod($tmp, 0664);
                rename($tmp, $dest);
            }
        }

        // FIX-B6c/D3: remove stale target files the new build no longer
        // contains (deleted pages) so they don't stay live. FULL builds only —
        // a partial batch's keep-list would condemn the rest of the site.
        // Dot-entries (.well-known for SSL, other infra) are preserved.
        if ($prune) {
            if ($this->isSharedPublicRoot($targetPath)) {
                // The shared docroot (ensodo.eu/public_html) also hosts every
                // slug site. Remove only what THIS site's previous build put
                // there — never anything we can't prove is ours.
                $this->pruneOwned($deployment, $targetPath, $keep);
            } else {
                $this->pruneStale($targetPath, $targetPath, $keep);
            }
        }

        $deployment->update(['artifact_path' => $stagingPath]);
    }

    private function isSharedPublicRoot(string $targetPath): bool
    {
        $public = (string) config('publishing.public_path');
        $a = realpath($targetPath);
        $b = $public !== '' ? realpath($public) : false;

        return $a !== false && $b !== false && $a === $b;
    }

    private function isInsideBuilds(string $targetPath): bool
    {
        $builds = realpath((string) config('publishing.staging_path'));
        $target = realpath($targetPath);

        return $builds !== false && $target !== false && str_starts_with($target . '/', $builds . '/');
    }

    /**
     * Prune for the shared docroot: delete only files the site's previous
     * live build defined and the new one no longer does (deleted pages).
     * Symlinks, dot-entries, other sites' folders and operator files are
     * never touched. Without the previous build's manifest nothing is
     * deleted — a stale page left live beats wiping someone else's site.
     */
    private function pruneOwned(Deployment $deployment, string $targetPath, array $keep): void
    {
        $previous = Deployment::where('site_id', $deployment->site_id)
            ->where('id', '!=', $deployment->id)
            ->where('status', 'live')
            ->whereNotNull('artifact_path')
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();
        $artifact = $previous?->artifact_path;
        if (!$artifact || !is_dir($artifact) || realpath($artifact) === realpath($targetPath)) {
            logger()->info("Shared-root deploy {$deployment->id}: no previous build manifest — skipping prune.");

            return;
        }

        $files = [];
        $dirs = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($artifact, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $rel = ltrim(substr($item->getPathname(), strlen($artifact)), '/');
            if ($rel === '' || str_starts_with($rel, '.') || str_contains($rel, '/.') || isset($keep[$rel])) {
                continue;
            }
            $item->isDir() ? $dirs[] = $rel : $files[] = $rel;
        }

        foreach ($files as $rel) {
            $live = "{$targetPath}/{$rel}";
            if ($this->crossesLink($targetPath, $rel) || !is_file($live)) {
                continue;
            }
            @unlink($live);
        }
        foreach ($dirs as $rel) {
            $live = "{$targetPath}/{$rel}";
            if ($this->crossesLink($targetPath, $rel) || !is_dir($live)) {
                continue;
            }
            if (count(scandir($live) ?: []) <= 2) {
                @rmdir($live);
            }
        }
    }

    /** True when any segment of $rel under $root is a symlink (another site's folder). */
    private function crossesLink(string $root, string $rel): bool
    {
        $path = $root;
        foreach (explode('/', $rel) as $segment) {
            $path .= '/' . $segment;
            if (is_link($path)) {
                return true;
            }
        }

        return false;
    }

    /** Delete files/dirs under $dir that aren't in $keep (relative to $root); never touch dot-entries. */
    private function pruneStale(string $root, string $dir, array $keep): void
    {
        $preserve = (array) config('publishing.preserve_paths', ['themes']);
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue; // preserve dotfiles/dot-dirs (SSL, infra, VCS)
            }
            // Reserved paths (e.g. /themes demo gallery) live outside the CMS
            // build and must survive full publishes.
            $rel = ltrim(str_replace($root, '', $dir . '/' . $entry), '/');
            if (in_array($rel, $preserve, true)) {
                continue;
            }
            $path = $dir . '/' . $entry;
            $relative = ltrim(str_replace($root, '', $path), '/');

            // A symlink inside the docroot is another site's folder (the
            // shared root hosts every slug site that way) or operator infra:
            // never descend into it and never remove it (F01/F03).
            if (is_link($path)) {
                continue;
            }

            if (is_dir($path)) {
                $this->pruneStale($root, $path, $keep);
                // remove now-empty directory that the build no longer defines
                if (!isset($keep[$relative]) && count(scandir($path) ?: []) <= 2) {
                    @rmdir($path);
                }
            } elseif (!isset($keep[$relative])) {
                @unlink($path);
            }
        }
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
