<?php

namespace App\Http\Middleware;

use App\Models\ModuleApiLog;
use App\Models\ModuleToken;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates module receiving endpoints by `Authorization: Bearer <token>`
 * against `module_tokens` (sha256 hash compare, not revoked, ability check).
 *
 * On success it establishes tenant context exactly like SetTenantFromAuth —
 * `SET app.current_tenant_id` from the token's tenant_id — and stashes the token
 * + tenant on the request so downstream middleware (EnsureModuleEnabled) and
 * controllers can read them. Every request is audited to module_api_logs.
 *
 * Usage: `module.token:drafts:create`.
 */
class AuthModuleToken
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $plaintext = $request->bearerToken();

        if (!$plaintext) {
            return $this->deny($request, null, $ability, ModuleApiLog::DENIED_AUTH, 401, 'missing_token');
        }

        $token = ModuleToken::where('token_hash', ModuleToken::hashToken($plaintext))->first();

        if (!$token || $token->isRevoked()) {
            return $this->deny($request, $token, $ability, ModuleApiLog::DENIED_AUTH, 401, 'invalid_token');
        }

        if (!$token->hasAbility($ability)) {
            return $this->deny($request, $token, $ability, ModuleApiLog::DENIED_ABILITY, 403, 'insufficient_ability');
        }

        $tenant = $this->establishTenantContext($token);

        $request->attributes->set('module_token', $token);
        $request->attributes->set('module_tenant', $tenant);

        $token->forceFill(['last_used_at' => now()])->save();

        $response = $next($request);

        $this->writeLog($request, $token, $ability, ModuleApiLog::GRANTED, $response->getStatusCode());

        return $response;
    }

    private function establishTenantContext(ModuleToken $token): ?Tenant
    {
        if (!$token->tenant_id) {
            return null;
        }

        $safe = preg_replace('/[^a-f0-9\-]/', '', $token->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$safe}'");

        return Tenant::find($token->tenant_id);
    }

    private function deny(
        Request $request,
        ?ModuleToken $token,
        string $ability,
        string $decision,
        int $status,
        string $error,
    ): Response {
        $this->writeLog($request, $token, $ability, $decision, $status);

        return response()->json(['error' => $error], $status);
    }

    private function writeLog(
        Request $request,
        ?ModuleToken $token,
        string $ability,
        string $decision,
        ?int $status,
    ): void {
        try {
            ModuleApiLog::create([
                'module_id' => $token?->module_id,
                'module_token_id' => $token?->getKey(),
                'tenant_id' => $token?->tenant_id,
                'method' => $request->getMethod(),
                'path' => substr($request->path(), 0, 512),
                'ability' => $ability,
                'decision' => $decision,
                'status_code' => $status,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
