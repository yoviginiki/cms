<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve user from session (available after Sanctum's stateful middleware)
        $user = Auth::guard('web')->user();

        if ($user && $user->tenant_id) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $user->tenant_id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
        }

        return $next($request);
    }
}
