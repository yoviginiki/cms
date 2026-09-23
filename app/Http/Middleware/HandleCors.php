<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\HandleCors as BaseHandleCors;
use Illuminate\Http\Request;

/**
 * Global CORS (credentialed, admin origins) — EXCEPT the public per-site
 * endpoints (H02, audit 2026-09-22). Those are called from each site's own
 * custom domain, which the global allow-list cannot know; the global handler
 * used to answer their preflight without Access-Control-Allow-Origin, so the
 * browser blocked every cross-origin form POST. PublicSiteCors owns them.
 */
class HandleCors extends BaseHandleCors
{
    /** Exact per-segment patterns ({seg} = one path segment, never a slash). */
    public const PUBLIC_PATTERNS = [
        '#^api/v1/public/[^/]+(/.*)?$#',
        '#^api/v1/sites/[^/]+/forms/submit$#',
        '#^api/v1/sites/[^/]+/forms/[^/]+/submit$#',
        '#^api/v1/sites/[^/]+/comments/[^/]+$#',
        '#^api/v1/sites/[^/]+/search$#',
        '#^api/v1/sites/[^/]+/search-beacon$#',
    ];

    public function handle($request, Closure $next)
    {
        if (self::isPublicSiteEndpoint($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    public static function isPublicSiteEndpoint(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        foreach (self::PUBLIC_PATTERNS as $re) {
            if (preg_match($re, $path)) {
                return true;
            }
        }

        return false;
    }
}
