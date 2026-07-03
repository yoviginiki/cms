<?php

namespace App\Services\Theme;

use App\Services\Theme\ValueObjects\ResolveRequest;
use App\Services\Theme\ValueObjects\ResolvedTheme;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates the full theme resolution pipeline:
 * Load layers → Merge → Resolve references → Return flat token map.
 */
class ThemeResolver
{
    public function __construct(
        private ThemeLoader $loader,
        private TokenMerger $merger,
        private ReferenceResolver $refs,
    ) {}

    public function resolve(ResolveRequest $request): ResolvedTheme
    {
        $cacheKey = $this->cacheKey($request);

        return Cache::remember($cacheKey, 3600, function () use ($request) {
            return $this->resolveFresh($request);
        });
    }

    public function resolveFresh(ResolveRequest $request): ResolvedTheme
    {
        $layers = $this->loader->loadLayers($request);
        $merged = $this->merger->merge($layers);
        $flat = $this->refs->flatten($merged);
        $hash = hash('sha256', json_encode($flat));

        return new ResolvedTheme($flat, $hash);
    }

    /**
     * Invalidate all cached resolutions for a tenant.
     */
    public function invalidateForTenant(string $tenantId): void
    {
        // Pattern-based invalidation (works with both Redis and file cache)
        // Since we can't do pattern delete on file cache, we use specific keys
        Cache::forget("theme:t:{$tenantId}");
    }

    /**
     * Invalidate for a specific site.
     */
    public function invalidateForSite(string $tenantId, string $siteId): void
    {
        Cache::forget($this->cacheKey(new ResolveRequest($tenantId, $siteId, mode: 'light')));
        Cache::forget($this->cacheKey(new ResolveRequest($tenantId, $siteId, mode: 'dark')));

        // Every caller of this method just changed the theme output (token
        // edit, override save, theme switch, version restore) — the published
        // static pages no longer match. Site-wide flag, expanded lazily.
        try {
            $site = \App\Models\Site::find($siteId);
            if ($site) {
                app(\App\Domain\References\Services\StalenessResolver::class)
                    ->markSiteStale($site, 'Theme changed — published pages use the previous design');
                app(\App\Domain\References\Services\ReferenceRecorder::class)
                    ->recomputeSiteScope($site);
            }
        } catch (\Throwable $e) {
            logger()->warning("Theme staleness marking failed for site {$siteId}: {$e->getMessage()}");
        }
    }

    private function cacheKey(ResolveRequest $r): string
    {
        return sprintf(
            'theme:resolved:t%s:s%s:p%s:b%s:m%s',
            $r->tenantId,
            $r->siteId ?? '0',
            $r->pageId ?? '0',
            $r->blockId ?? '0',
            $r->mode,
        );
    }
}
