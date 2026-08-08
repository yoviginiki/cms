<?php

namespace App\Http\Middleware;

use App\Domain\Modules\Services\ModuleRegistry;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group on a module being enabled. Usage: `module:culture-engine`.
 *
 * Tenant resolution: the authenticated user's tenant on user-auth routes, or
 * the `module_tenant` request attribute placed by AuthModuleToken on
 * token-auth routes (which may be null for a platform-level token — then only
 * the global flag is required).
 *
 * Disabled → 403 JSON `{ "error": "module_disabled", "module": <key> }`.
 * This runs AFTER any auth middleware but is independent of permission checks.
 */
class EnsureModuleEnabled
{
    public function __construct(private ModuleRegistry $registry)
    {
    }

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (!$this->registry->isEnabled($key, $this->resolveTenant($request))) {
            return response()->json([
                'error' => 'module_disabled',
                'module' => $key,
            ], 403);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $user = $request->user();
        if ($user && $user->tenant_id) {
            return $user->tenant;
        }

        $fromToken = $request->attributes->get('module_tenant');

        return $fromToken instanceof Tenant ? $fromToken : null;
    }
}
