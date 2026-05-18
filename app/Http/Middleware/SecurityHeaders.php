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

        // Allow same-origin framing for studio/preview iframes, deny cross-origin
        $isFrameable = str_contains($request->path(), 'studio/frame')
            || str_contains($request->path(), '/preview');
        $response->headers->set('X-Frame-Options', $isFrameable ? 'SAMEORIGIN' : 'DENY');

        return $response;
    }
}
