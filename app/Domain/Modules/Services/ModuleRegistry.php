<?php

namespace App\Domain\Modules\Services;

use App\Models\Module;
use App\Models\ModuleTenant;
use App\Models\Tenant;

/**
 * Resolves module enablement across the two on/off levels and provides the
 * mutation API used by the settings screens.
 *
 * Effective enablement = `modules.enabled_globally` AND (for a given tenant)
 * that tenant's `module_tenant.enabled`. Permission checks are SEPARATE — they
 * live in the role-hierarchy gates (ModulePermissions / EnsureRole), not here.
 *
 * Registered as a singleton (see AppServiceProvider) so its lookups are cached
 * for the lifetime of a request; every mutation flushes that cache.
 */
class ModuleRegistry
{
    /** @var array<string, Module|null> */
    private array $moduleCache = [];

    /** @var array<string, bool> */
    private array $enabledCache = [];

    public function module(string $key): ?Module
    {
        if (!array_key_exists($key, $this->moduleCache)) {
            $this->moduleCache[$key] = Module::where('key', $key)->first();
        }

        return $this->moduleCache[$key];
    }

    /**
     * Is $key effectively enabled? With no tenant, this is the platform-level
     * answer (global flag only). With a tenant, both levels must be true.
     * The tenant pivot read is RLS-scoped to the current tenant context.
     */
    public function isEnabled(string $key, ?Tenant $tenant = null): bool
    {
        $tenantId = $tenant?->getKey();
        $cacheKey = $key . ':' . ($tenantId ?? '-');

        if (array_key_exists($cacheKey, $this->enabledCache)) {
            return $this->enabledCache[$cacheKey];
        }

        $module = $this->module($key);
        $enabled = false;

        if ($module && $module->enabled_globally) {
            if ($tenantId === null) {
                $enabled = true;
            } else {
                $enabled = ModuleTenant::query()
                    ->where('module_id', $module->id)
                    ->where('tenant_id', $tenantId)
                    ->where('enabled', true)
                    ->exists();
            }
        }

        return $this->enabledCache[$cacheKey] = $enabled;
    }

    public function enableGlobally(string $key): void
    {
        $this->setGlobal($key, true);
    }

    public function disableGlobally(string $key): void
    {
        $this->setGlobal($key, false);
    }

    public function enableForTenant(string $key, Tenant $tenant): void
    {
        $this->setForTenant($key, $tenant, true);
    }

    public function disableForTenant(string $key, Tenant $tenant): void
    {
        $this->setForTenant($key, $tenant, false);
    }

    private function setGlobal(string $key, bool $enabled): void
    {
        $module = $this->module($key);
        if (!$module) {
            return;
        }

        $module->update(['enabled_globally' => $enabled]);
        $this->flush();
    }

    private function setForTenant(string $key, Tenant $tenant, bool $enabled): void
    {
        $module = $this->module($key);
        if (!$module) {
            return;
        }

        ModuleTenant::updateOrCreate(
            ['module_id' => $module->id, 'tenant_id' => $tenant->getKey()],
            ['enabled' => $enabled],
        );
        $this->flush();
    }

    /** Drop the per-request cache after any mutation. */
    public function flush(): void
    {
        $this->moduleCache = [];
        $this->enabledCache = [];
    }
}
