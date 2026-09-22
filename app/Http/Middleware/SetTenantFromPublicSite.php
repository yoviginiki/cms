<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant context for UNAUTHENTICATED public endpoints addressed by site id
 * (search islands, forms, comments). Without a GUC the sites RLS policy hides
 * every row, so route binding 404s — the reason the original public routes
 * were dead in production.
 *
 * The site→tenant mapping can't be read directly (RLS chicken-and-egg), so it
 * resolves by scanning tenants (no RLS on that table) once and caching the
 * mapping forever — a site's tenant never changes. Registered BEFORE
 * SubstituteBindings in the middleware priority list so implicit {site}
 * binding works on public routes.
 */
class SetTenantFromPublicSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $siteId = (string) $request->route('site');
        // F22: shared resolver — cached mapping, GUC cleared on a miss.
        $tenantId = app(\App\Domain\Tenancy\PublicTenantResolver::class)->tenantForSite($siteId);
        if (!$tenantId) {
            \App\Domain\Tenancy\PublicTenantResolver::clear();
            abort(404);
        }
        \App\Domain\Tenancy\PublicTenantResolver::set($tenantId);

        return $next($request);
    }
}
