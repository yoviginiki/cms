<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            abort(403, 'No tenant context.');
        }

        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $user->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        return $next($request);
    }
}
