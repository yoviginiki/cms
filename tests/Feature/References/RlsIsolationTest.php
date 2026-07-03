<?php

namespace Tests\Feature\References;

use App\Models\EntityReference;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Row-level security on entity_references: one tenant can neither read nor
 * write another tenant's edges.
 */
class RlsIsolationTest extends TestCase
{
    public function test_tenants_cannot_read_each_others_edges(): void
    {
        // Tenant A writes an edge
        $this->setTenantScope($this->owner);
        $siteA = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $pageA = Page::factory()->published()->create(['site_id' => $siteA->id]);
        EntityReference::create([
            'site_id' => $siteA->id,
            'source_type' => 'page', 'source_id' => $pageA->id,
            'target_type' => 'asset', 'target_id' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'uses_asset',
        ]);
        $this->assertSame(1, EntityReference::count());

        // Tenant B sees nothing
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);
        $this->assertSame(0, EntityReference::count());

        // Back to tenant A: still there
        $this->setTenantScope($this->owner);
        $this->assertSame(1, EntityReference::count());
    }

    public function test_tenant_cannot_write_edges_into_another_tenants_site(): void
    {
        $this->setTenantScope($this->owner);
        $siteA = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        // Switch to tenant B and try to write an edge scoped to A's site —
        // the RLS WITH CHECK must reject it
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('entity_references')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'site_id' => $siteA->id,
            'source_type' => 'page', 'source_id' => (string) \Illuminate\Support\Str::uuid(),
            'target_type' => 'asset', 'target_id' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'uses_asset',
        ]);
    }
}
