<?php

namespace App\Services\Theme;

use App\Services\Theme\ValueObjects\ResolveRequest;
use App\Services\Theme\ValueObjects\ResolvedTheme;

/**
 * Request-scoped singleton that provides the resolved theme for the current request.
 */
class CurrentTheme
{
    private ?ResolvedTheme $resolved = null;

    public function __construct(
        private ThemeResolver $resolver,
    ) {}

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->resolved()->get($path, $default);
    }

    public function has(string $path): bool
    {
        return $this->resolved()->has($path);
    }

    public function resolved(): ResolvedTheme
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        // Build a resolve request from current context
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            // Fallback: empty theme
            return $this->resolved = new ResolvedTheme([], hash('sha256', ''));
        }

        $request = new ResolveRequest(
            tenantId: $tenantId,
            siteId: request()?->attributes?->get('site.id'),
            mode: request()?->cookie('theme_mode', 'light') ?? 'light',
        );

        return $this->resolved = $this->resolver->resolve($request);
    }

    /**
     * Force re-resolve with specific overrides (for live preview).
     */
    public function setResolved(ResolvedTheme $theme): void
    {
        $this->resolved = $theme;
    }

    private function getTenantId(): ?string
    {
        // Try to get from the current site
        $siteId = request()?->attributes?->get('site.id');
        if ($siteId) {
            $site = \App\Models\Site::find($siteId);
            return $site?->tenant_id;
        }

        // Try from DB context
        try {
            $result = \Illuminate\Support\Facades\DB::selectOne("SELECT current_setting('app.current_tenant_id', true) as tid");
            return $result?->tid ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
