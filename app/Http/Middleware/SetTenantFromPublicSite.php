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
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $siteId)) {
            abort(404);
        }

        $tenantId = Cache::get("site_tenant:{$siteId}");
        if ($tenantId === null) {
            foreach (Tenant::pluck('id') as $candidate) {
                $safe = preg_replace('/[^a-f0-9\-]/', '', $candidate);
                DB::unprepared("SET app.current_tenant_id = '{$safe}'");
                if (Site::where('id', $siteId)->exists()) {
                    $tenantId = $candidate;
                    Cache::forever("site_tenant:{$siteId}", $tenantId);
                    break;
                }
            }
        }

        if (!$tenantId) {
            abort(404);
        }

        $safe = preg_replace('/[^a-f0-9\-]/', '', $tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$safe}'");

        return $next($request);
    }
}
