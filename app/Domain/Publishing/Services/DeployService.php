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
            $tenantBase = config('publishing.tenant_base', '/home/cytechno/web');
            $safeDomain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $site->custom_domain);
            if (!$safeDomain || str_contains($safeDomain, '..')) {
                throw new \RuntimeException("Invalid custom domain: {$site->custom_domain}");
            }
            $targetPath = $tenantBase . '/' . $safeDomain . '/public_html';
            if (!is_dir($targetPath)) {
                throw new \RuntimeException("Deploy target does not exist: {$targetPath}.");
            }
        } else {
            $targetPath = config('publishing.public_path') . '/' . $site->deploySlug();
            // If the docroot is currently a symlink to a full build, per-file
            // writes would mutate that build's directory — still correct
            // content-wise, but resolve it so paths land where they're served
            if (is_link($targetPath)) {
                $targetPath = readlink($targetPath);
            }
        }

        // MERGE ONLY — a partial staging tree holds a few pages; pruning
        // against it would delete the rest of the live site. Stale files from
        // slug renames are StalePathCleaner's job, not the deploy's.
        $this->copyDeploy($stagingPath, $targetPath, $deployment, prune: false);
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
            $publicPath = $basePath . '/' . $site->deploySlug();
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
            $deploymentId = basename((string) readlink($link));
            $owner = \App\Models\Deployment::whereKey($deploymentId)->value('site_id');
            if ($owner === $site->id) {
                unlink($link);
            }
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
    private function copyDeploy(string $stagingPath, string $targetPath, Deployment $deployment, bool $prune = true): void
    {
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
            $this->pruneStale($targetPath, $targetPath, $keep);
        }

        $deployment->update(['artifact_path' => $stagingPath]);
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
