<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Modules\Services\ModuleRegistry;
use App\Domain\Modules\Support\ModulePermissions;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Settings → Modules. Read is open to any authenticated tenant user (it powers
 * nav gating and returns only that user's abilities + effective enablement);
 * every mutation is gated on the role-threshold abilities (docs: RBAC).
 */
class ModuleController extends Controller
{
    public function __construct(private ModuleRegistry $registry)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;

        $abilities = [
            'use' => Gate::allows(ModulePermissions::USE),
            'manage' => Gate::allows(ModulePermissions::MANAGE),
            'administer' => Gate::allows(ModulePermissions::ADMINISTER),
        ];

        $modules = Module::orderBy('name')->get()->map(function (Module $module) use ($tenant, $abilities) {
            $tenantPivot = ModuleTenant::query()
                ->where('module_id', $module->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            $row = [
                'key' => $module->key,
                'name' => $module->name,
                'description' => $module->description,
                'enabled_globally' => $module->enabled_globally,
                'enabled_for_tenant' => (bool) ($tenantPivot?->enabled),
                'effective_enabled' => $this->registry->isEnabled($module->key, $tenant),
            ];

            // Management-only detail.
            if ($abilities['manage'] || $abilities['administer']) {
                $row['settings_schema'] = $module->settings_schema;
                $row['settings'] = $tenantPivot?->settings ?? [];
            }

            return $row;
        });

        return response()->json([
            'abilities' => $abilities,
            'modules' => $modules,
        ]);
    }

    public function setGlobal(Request $request, Module $module): JsonResponse
    {
        $this->authorize(ModulePermissions::ADMINISTER);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $data['enabled']
            ? $this->registry->enableGlobally($module->key)
            : $this->registry->disableGlobally($module->key);

        return response()->json(['enabled_globally' => (bool) $data['enabled']]);
    }

    public function setTenant(Request $request, Module $module): JsonResponse
    {
        $this->authorize(ModulePermissions::MANAGE);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $tenant = $request->user()->tenant;

        // A tenant can only opt in while the module is globally enabled.
        if ($data['enabled'] && !$module->enabled_globally) {
            return response()->json([
                'error' => 'module_not_globally_enabled',
                'message' => 'This module is not enabled at the platform level.',
            ], 422);
        }

        $data['enabled']
            ? $this->registry->enableForTenant($module->key, $tenant)
            : $this->registry->disableForTenant($module->key, $tenant);

        return response()->json(['enabled_for_tenant' => (bool) $data['enabled']]);
    }

    public function updateSettings(Request $request, Module $module): JsonResponse
    {
        $this->authorize(ModulePermissions::MANAGE);

        $data = $request->validate(['settings' => ['present', 'array']]);
        $tenant = $request->user()->tenant;

        $pivot = ModuleTenant::updateOrCreate(
            ['module_id' => $module->id, 'tenant_id' => $tenant->id],
            ['settings' => $data['settings']],
        );

        return response()->json(['settings' => $pivot->settings]);
    }
}
