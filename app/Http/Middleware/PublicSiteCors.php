<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the public collections API, locked to the requesting site's own
 * domains: its custom domain, its slug subdomain, and the CMS origin
 * (admin preview). Runs after SetTenantFromPublicSite so the site resolves.
 * Read-only API → GET/HEAD only, no credentials, no preflight surface.
 */
class PublicSiteCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $origin = $request->headers->get('Origin');
        if (!$origin) {
            return $response;
        }

        $site = $request->route('site');
        if (!$site instanceof Site) {
            return $response;
        }

        $allowed = array_filter([
            $site->custom_domain ? "https://{$site->custom_domain}" : null,
            $site->custom_domain ? "https://www.{$site->custom_domain}" : null,
            "https://{$site->slug}.ensodo.eu",
            rtrim((string) config('app.url'), '/'),
        ]);

        if (in_array($origin, $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD');
        }

        return $response;
    }
}
