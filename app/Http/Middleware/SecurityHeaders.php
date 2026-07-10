<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS — the admin app is served over https only (FIX-A4b).
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Allow same-origin framing for the various preview/render iframes
        // (theme studio frames, page preview, and the magazine/issue-studio
        // "dtp-preview" spread render — note the hyphen, so a "/preview" match
        // alone misses it). SAMEORIGIN still blocks cross-origin clickjacking.
        $path = $request->path();
        $isFrameable = str_contains($path, 'studio/frame')
            || str_contains($path, 'preview'); // /preview, dtp-preview, magazine preview
        $response->headers->set('X-Frame-Options', $isFrameable ? 'SAMEORIGIN' : 'DENY');

        return $response;
    }
}
