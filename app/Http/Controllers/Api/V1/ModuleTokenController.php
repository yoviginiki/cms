<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Modules\Support\ModulePermissions;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token management for a module, scoped to the acting tenant. Gated on
 * `module.culture.manage` (admin+). The plaintext token is returned exactly
 * once, on creation; thereafter only metadata is exposed (never the hash).
 */
class ModuleTokenController extends Controller
{
    public function index(Request $request, Module $module): JsonResponse
    {
        $this->authorize(ModulePermissions::MANAGE);

        $tokens = ModuleToken::query()
            ->where('module_id', $module->id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'revoked_at', 'created_at']);

        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request, Module $module): JsonResponse
    {
        $this->authorize(ModulePermissions::MANAGE);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'max:100'],
        ]);

        [$token, $plaintext] = ModuleToken::issue([
            'module_id' => $module->id,
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'abilities' => $data['abilities'] ?? ['drafts:create'],
        ]);

        return response()->json([
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
            ],
            // Shown exactly once — never retrievable again.
            'plaintext' => $plaintext,
        ], 201);
    }

    public function destroy(Request $request, Module $module, ModuleToken $token): JsonResponse
    {
        $this->authorize(ModulePermissions::MANAGE);

        abort_if(
            $token->module_id !== $module->id || $token->tenant_id !== $request->user()->tenant_id,
            404,
        );

        // Revoke rather than delete, so the audit trail's FK survives.
        $token->forceFill(['revoked_at' => now()])->save();

        return response()->json(['revoked' => true]);
    }
}
