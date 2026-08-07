<?php

namespace Tests\Unit\Modules;

use App\Domain\Modules\Services\ModuleRegistry;
use App\Models\Module;
use App\Models\ModuleTenant;
use Tests\TestCase;

/**
 * Resolution matrix for ModuleRegistry::isEnabled — the two on/off levels
 * (global flag × per-tenant pivot) and the mutation API's cache-flush.
 */
class ModuleRegistryTest extends TestCase
{
    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->registry = app(ModuleRegistry::class);
    }

    private function makeModule(bool $global): Module
    {
        return Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'enabled_globally' => $global,
        ]);
    }

    private function setPivot(Module $module, bool $enabled): void
    {
        ModuleTenant::create([
            'module_id' => $module->id,
            'tenant_id' => $this->tenant->id,
            'enabled' => $enabled,
        ]);
    }

    public function test_unknown_module_is_disabled(): void
    {
        $this->assertFalse($this->registry->isEnabled('nope'));
        $this->assertFalse($this->registry->isEnabled('nope', $this->tenant));
    }

    public function test_global_off_is_disabled_at_every_level(): void
    {
        $module = $this->makeModule(global: false);
        $this->setPivot($module, enabled: true);
        $this->registry->flush();

        $this->assertFalse($this->registry->isEnabled('culture-engine'));
        $this->assertFalse($this->registry->isEnabled('culture-engine', $this->tenant));
    }

    public function test_global_on_no_tenant_is_platform_enabled(): void
    {
        $this->makeModule(global: true);
        $this->registry->flush();

        $this->assertTrue($this->registry->isEnabled('culture-engine'));
    }

    public function test_global_on_but_tenant_pivot_absent_is_disabled_for_tenant(): void
    {
        $this->makeModule(global: true);
        $this->registry->flush();

        $this->assertFalse($this->registry->isEnabled('culture-engine', $this->tenant));
    }

    public function test_global_on_and_tenant_pivot_enabled_is_enabled(): void
    {
        $module = $this->makeModule(global: true);
        $this->setPivot($module, enabled: true);
        $this->registry->flush();

        $this->assertTrue($this->registry->isEnabled('culture-engine', $this->tenant));
    }

    public function test_global_on_but_tenant_pivot_disabled_is_disabled(): void
    {
        $module = $this->makeModule(global: true);
        $this->setPivot($module, enabled: false);
        $this->registry->flush();

        $this->assertFalse($this->registry->isEnabled('culture-engine', $this->tenant));
    }

    public function test_mutation_api_flushes_cache(): void
    {
        $this->makeModule(global: false);

        // Prime the cache with the disabled answer.
        $this->assertFalse($this->registry->isEnabled('culture-engine'));

        // Toggling through the API must invalidate that cached answer.
        $this->registry->enableGlobally('culture-engine');
        $this->assertTrue($this->registry->isEnabled('culture-engine'));

        $this->registry->enableForTenant('culture-engine', $this->tenant);
        $this->assertTrue($this->registry->isEnabled('culture-engine', $this->tenant));

        $this->registry->disableForTenant('culture-engine', $this->tenant);
        $this->assertFalse($this->registry->isEnabled('culture-engine', $this->tenant));

        $this->registry->disableGlobally('culture-engine');
        $this->assertFalse($this->registry->isEnabled('culture-engine'));
    }
}
