<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates DTP designer endpoints behind feature flag.
 */
class RequireDtpDesigner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('features.magazine_dtp_designer_enabled', false)) {
            return response()->json([
                'message' => 'DTP Designer is not enabled for this site.',
            ], 404);
        }

        return $next($request);
    }
}
