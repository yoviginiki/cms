<?php

namespace App\Domain\Publishing\Services;

use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Single authority for WHERE a site's live output lives and WHETHER the site
 * is allowed to touch it (audit 2026-09-22, F01/F03).
 *
 * Two hosting shapes exist:
 *  - slug-hosted:    {public_path}/{deploySlug}  (usually a symlink to a build)
 *  - custom domain:  {tenant_base}/{domain}/public_html (a Hestia docroot)
 *
 * The shared public root is itself the docroot of one custom-domain site
 * (ensodo.eu), so every other site's folder sits INSIDE it as a symlink.
 * Nothing here ever walks the shared root, follows a symlink, or treats the
 * mere existence of a directory as authorization:
 *  - reserved (admin) domains and slugs are refused everywhere;
 *  - a custom-domain docroot is claimed on first deploy with an ownership
 *    marker (`.cms-site` = site id); a marker naming another site refuses
 *    the deploy;
 *  - a slug folder is only ours when its symlink resolves to one of OUR
 *    deployments (or, legacy copy strategy, it is a real dir named after us).
 */
final class DeployTargetResolver
{
    public const MARKER = '.cms-site';

    /** Reserved host names that may never become a site's custom domain. */
    public function reservedDomains(): array
    {
        $configured = (array) config('publishing.reserved_domains', []);
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $stateful = config('sanctum.stateful', []);
        $stateful = is_array($stateful) ? $stateful : explode(',', (string) $stateful);
        $stateful = array_map(fn ($h) => preg_replace('~:\d+$~', '', trim((string) $h)), $stateful);

        return array_values(array_unique(array_filter(array_map(
            fn ($d) => $this->normalizeDomain((string) $d),
            array_merge($configured, [$appHost], $stateful),
        ))));
    }

    public function isReservedDomain(?string $domain): bool
    {
        $norm = $this->normalizeDomain((string) $domain);

        return $norm !== null && in_array($norm, $this->reservedDomains(), true);
    }

    public function reservedSlugs(): array
    {
        return array_map('strtolower', (array) config('publishing.reserved_slugs', ['sys', 'admin', 'api', 'login', 'register']));
    }

    public function isReservedSlug(?string $slug): bool
    {
        return $slug !== null && in_array(strtolower($slug), $this->reservedSlugs(), true);
    }

    /**
     * Canonical form of a host name, or null when it can't be one
     * (separators, traversal, whitespace, empty labels). Never trust the
     * request regex alone: rows can reach the deploy layer from imports/CLI.
     */
    public function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if ($domain === '' || strlen($domain) > 253) {
            return null;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $domain)) {
            return null;
        }

        return $domain;
    }

    /** True when the site deploys into its own Hestia docroot. */
    public function usesCustomDomain(Site $site): bool
    {
        return (string) $site->custom_domain !== '';
    }

    /**
     * The custom-domain docroot path for a site (not verified on disk).
     * Throws when the domain is malformed or reserved.
     */
    public function customDomainDocroot(Site $site): string
    {
        $domain = $this->normalizeDomain((string) $site->custom_domain);
        if ($domain === null) {
            throw new \RuntimeException("Invalid custom domain: {$site->custom_domain}");
        }
        if ($this->isReservedDomain($domain)) {
            throw new \RuntimeException("Deploy refused: '{$domain}' is a reserved domain of the installation.");
        }
        $tenantBase = rtrim((string) config('publishing.tenant_base', '/home/cytechno/web'), '/');
        if ($tenantBase === '') {
            throw new \RuntimeException('publishing.tenant_base is not configured.');
        }

        return "{$tenantBase}/{$domain}/public_html";
    }

    /** The slug-hosted folder under the shared public root (not verified on disk). */
    public function slugDocroot(Site $site): string
    {
        $slug = $site->deploySlug();
        if ($this->isReservedSlug($slug)) {
            throw new \RuntimeException("Deploy refused: '{$slug}' is a reserved folder of the installation.");
        }
        $public = rtrim((string) config('publishing.public_path'), '/');
        if ($public === '') {
            throw new \RuntimeException('publishing.public_path is not configured.');
        }

        return "{$public}/{$slug}";
    }

    /**
     * Live docroot for reading/cleaning purposes (StaticCleaner & co.).
     * Returns null when the site has no resolvable, owned target on disk.
     */
    public function tryLiveDocroot(Site $site): ?string
    {
        try {
            if ($this->usesCustomDomain($site)) {
                $path = $this->customDomainDocroot($site);
                if (!is_dir($path) || !$this->isContained($path, config('publishing.tenant_base'))) {
                    return null;
                }
                if (!$this->markerAllows($site, $path)) {
                    return null;
                }

                return $path;
            }

            $path = $this->slugDocroot($site);

            return $this->ownsSlugFolder($site, $path) ? $path : null;
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Resolve and AUTHORIZE the custom-domain deploy target for a full or
     * partial deploy: it must exist (Hestia provisions it — we never create
     * a docroot), sit inside tenant_base, and be unclaimed or claimed by us.
     * On success the target is claimed for this site.
     */
    public function authorizeCustomDomainTarget(Site $site): string
    {
        $path = $this->customDomainDocroot($site);

        if (!is_dir($path) || is_link(dirname($path)) || is_link($path)) {
            throw new \RuntimeException("Deploy target does not exist: {$path}. Create the domain in Hestia first.");
        }
        if (!$this->isContained($path, config('publishing.tenant_base'))) {
            throw new \RuntimeException("Deploy target {$path} is outside the tenant web root.");
        }
        $this->claim($site, $path);

        return $path;
    }

    /**
     * Claim a docroot for a site with an ownership marker. Atomic on first
     * claim (O_EXCL); a marker naming another site refuses the operation.
     */
    public function claim(Site $site, string $docroot): void
    {
        $marker = $docroot . '/' . self::MARKER;
        $handle = @fopen($marker, 'x');
        if ($handle !== false) {
            fwrite($handle, $site->id);
            fclose($handle);
            @chmod($marker, 0644);

            return;
        }
        if (!$this->markerAllows($site, $docroot)) {
            throw new \RuntimeException("Deploy refused: {$docroot} is provisioned for another site.");
        }
    }

    /** True when there is no marker, or the marker names this site. */
    public function markerAllows(Site $site, string $docroot): bool
    {
        $marker = $docroot . '/' . self::MARKER;
        if (!is_file($marker)) {
            return true;
        }
        $owner = trim((string) @file_get_contents($marker));

        return $owner === '' || $owner === (string) $site->id;
    }

    /**
     * A slug folder is ours when its symlink resolves to one of our
     * deployments, or (legacy copy/rename strategy) it is a real directory.
     * Never follows the link to inspect content.
     */
    public function ownsSlugFolder(Site $site, string $path): bool
    {
        if (!$this->isContained(dirname($path) . '/' . basename($path), config('publishing.public_path'), followLast: false)) {
            return false;
        }
        if (is_link($path)) {
            return $this->linkOwner($path) === (string) $site->id;
        }

        return is_dir($path) && basename($path) === $site->deploySlug();
    }

    /**
     * Remove a site's live output and ONLY that (F01):
     *  - slug-hosted: unlink every symlink in the public root that points at
     *    one of this site's builds (the builds themselves stay under
     *    retention), or delete the legacy real directory named after us;
     *  - custom domain: delete the files the LAST live artifact defines,
     *    then the directories it emptied. Dot-entries, symlinks, unmanaged
     *    files and the docroot itself are never touched.
     *
     * @return array{removed:int, target:?string}
     */
    public function clearLiveOutput(Site $site): array
    {
        if ($this->usesCustomDomain($site)) {
            return $this->clearCustomDomainOutput($site);
        }

        $public = rtrim((string) config('publishing.public_path'), '/');
        if ($public === '' || !is_dir($public)) {
            return ['removed' => 0, 'target' => null];
        }

        $removed = 0;
        $target = null;
        foreach (scandir($public) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $path = "{$public}/{$entry}";
            if (is_link($path)) {
                if ($this->linkOwner($path) === (string) $site->id) {
                    unlink($path);
                    $removed++;
                    $target = $path;
                }
            } elseif ($entry === $site->deploySlug() && is_dir($path) && !$this->isReservedSlug($entry)) {
                File::deleteDirectory($path);
                $removed++;
                $target = $path;
            }
        }

        return ['removed' => $removed, 'target' => $target];
    }

    private function clearCustomDomainOutput(Site $site): array
    {
        $docroot = $this->tryLiveDocroot($site);
        if ($docroot === null) {
            return ['removed' => 0, 'target' => null];
        }

        $deployment = Deployment::where('site_id', $site->id)
            ->where('status', 'live')
            ->whereNotNull('artifact_path')
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();
        $artifact = $deployment?->artifact_path;
        if (!$artifact || !is_dir($artifact)) {
            // Without a manifest of what we wrote there we cannot tell our
            // files from the operator's — refuse to guess.
            return ['removed' => 0, 'target' => $docroot];
        }

        $files = [];
        $dirs = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($artifact, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $rel = ltrim(substr($item->getPathname(), strlen($artifact)), '/');
            if ($rel === '' || str_starts_with($rel, '.') || str_contains($rel, '/.')) {
                continue; // never manage dot-entries (SSL, infra, marker)
            }
            $item->isDir() ? $dirs[] = $rel : $files[] = $rel;
        }

        $removed = 0;
        foreach ($files as $rel) {
            $live = "{$docroot}/{$rel}";
            if (is_link($live) || !is_file($live) || !$this->isContained($live, $docroot, followLast: false)) {
                continue;
            }
            @unlink($live);
            $removed++;
        }
        foreach ($dirs as $rel) {
            $live = "{$docroot}/{$rel}";
            if (is_link($live) || !is_dir($live)) {
                continue;
            }
            if (count(scandir($live) ?: []) <= 2) {
                @rmdir($live);
            }
        }

        return ['removed' => $removed, 'target' => $docroot];
    }

    /**
     * Site id owning the build a public-root symlink points at, or null.
     * Symlinks named after anything but a deployment UUID (operator links,
     * legacy folders) are never ours — and never sent to the database.
     */
    public function linkOwner(string $link): ?string
    {
        $deploymentId = basename((string) readlink($link));
        if (!Str::isUuid($deploymentId)) {
            return null;
        }
        $owner = Deployment::whereKey($deploymentId)->value('site_id');

        return $owner === null ? null : (string) $owner;
    }

    /**
     * True when $path resolves inside $root. With followLast=false the
     * final component is not resolved (so a symlink entry counts by where
     * it sits, not where it points).
     */
    public function isContained(string $path, ?string $root, bool $followLast = true): bool
    {
        $realRoot = $root ? realpath($root) : false;
        if ($realRoot === false) {
            return false;
        }
        $realPath = $followLast ? realpath($path) : (($d = realpath(dirname($path))) ? $d . '/' . basename($path) : false);
        if ($realPath === false) {
            return false;
        }

        return $realPath === $realRoot || str_starts_with($realPath, rtrim($realRoot, '/') . '/');
    }
}
