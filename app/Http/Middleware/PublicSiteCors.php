<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for public per-site endpoints, locked to the requesting site's own
 * origins: its custom domain (+www), its slug subdomain and the CMS origin
 * (admin preview). Runs after SetTenantFromPublicSite so the site resolves.
 *
 * H02 (audit 2026-09-22): answers the PREFLIGHT itself (the published
 * contact-form sends FormData + X-Requested-With, which is not a simple
 * request), never allows credentials (these endpoints need no cookies), and
 * advertises only GET/HEAD/POST.
 */
class PublicSiteCors
{
    private const ALLOWED_METHODS = ['GET', 'HEAD', 'POST'];
    private const ALLOWED_HEADERS = 'Content-Type, Accept, X-Requested-With';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $site = $request->route('site');
        $allowed = $origin !== '' && $site instanceof Site && in_array($origin, self::originsFor($site), true);

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            $wanted = strtoupper((string) $request->headers->get('Access-Control-Request-Method', ''));
            if ($allowed && in_array($wanted, self::ALLOWED_METHODS, true)) {
                $this->decorate($response, $origin);
                $response->headers->set('Access-Control-Allow-Headers', self::ALLOWED_HEADERS);
                $response->headers->set('Access-Control-Max-Age', '600');
            }
            $response->headers->set('Vary', 'Origin');

            return $response;
        }

        $response = $next($request);
        if ($allowed) {
            $this->decorate($response, $origin);
        }

        return $response;
    }

    /** @return array<int,string> */
    public static function originsFor(Site $site): array
    {
        return array_values(array_filter([
            $site->custom_domain ? "https://{$site->custom_domain}" : null,
            $site->custom_domain ? "https://www.{$site->custom_domain}" : null,
            "https://{$site->slug}.ensodo.eu",
            rtrim((string) config('app.url'), '/'),
        ]));
    }

    private function decorate(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', self::ALLOWED_METHODS));
        $response->headers->remove('Access-Control-Allow-Credentials');
        $response->headers->set('Vary', 'Origin');
    }
}
