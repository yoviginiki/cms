<?php

namespace Tests\Feature\Modules;

use App\Domain\Modules\Jobs\GuardsModuleEnabled;
use App\Models\Module;
use App\Models\ModuleTenant;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleFrameworkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ad-hoc route gated only by the module middleware; the request runs on
        // the test's connection where the tenant GUC is already set.
        Route::middleware(['module:culture-engine'])
            ->get('/_test/culture-ping', fn () => response()->json(['ok' => true]));
    }

    private function enableCultureForTenant(): void
    {
        $module = Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'enabled_globally' => true,
        ]);

        ModuleTenant::create([
            'module_id' => $module->id,
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);
    }

    public function test_middleware_returns_403_when_module_disabled(): void
    {
        $this->setTenantScope($this->owner);
        // module row absent entirely → disabled
        $response = $this->actingAsOwner()->getJson('/_test/culture-ping');

        $response->assertStatus(403)
            ->assertJson(['error' => 'module_disabled', 'module' => 'culture-engine']);
    }

    public function test_middleware_returns_403_when_global_on_but_tenant_off(): void
    {
        $this->setTenantScope($this->owner);
        $module = Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'enabled_globally' => true,
        ]);
        ModuleTenant::create([
            'module_id' => $module->id,
            'tenant_id' => $this->tenant->id,
            'enabled' => false,
        ]);

        $this->actingAsOwner()->getJson('/_test/culture-ping')->assertStatus(403);
    }

    public function test_middleware_passes_when_module_enabled(): void
    {
        $this->setTenantScope($this->owner);
        $this->enableCultureForTenant();

        $this->actingAsOwner()->getJson('/_test/culture-ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_module_job_noops_when_disabled_and_runs_when_enabled(): void
    {
        $this->setTenantScope($this->owner);

        CultureProbeJob::$ran = 0;

        // Disabled → no-op (no throw, no action).
        (new CultureProbeJob($this->tenant->id))->handle();
        $this->assertSame(0, CultureProbeJob::$ran, 'job should no-op while module disabled');

        // Enable, then it runs.
        $this->enableCultureForTenant();
        app(\App\Domain\Modules\Services\ModuleRegistry::class)->flush();

        (new CultureProbeJob($this->tenant->id))->handle();
        $this->assertSame(1, CultureProbeJob::$ran, 'job should run once module enabled');
    }

    public function test_seeder_registers_culture_engine_disabled(): void
    {
        $this->seed(ModuleSeeder::class);

        $module = Module::where('key', 'culture-engine')->first();
        $this->assertNotNull($module);
        $this->assertFalse($module->enabled_globally);
        $this->assertSame('Culture Engine', $module->name);
    }
}

/**
 * A throwaway job that exercises the GuardsModuleEnabled trait: it performs its
 * "work" (bumping a static counter) only when the module resolves enabled.
 */
class CultureProbeJob
{
    use GuardsModuleEnabled;

    public static int $ran = 0;

    public function __construct(private string $tenantId)
    {
    }

    public function handle(): void
    {
        $tenant = \App\Models\Tenant::find($this->tenantId);

        if (!$this->moduleEnabledOrLog('culture-engine', $tenant)) {
            return;
        }

        self::$ran++;
    }
}
