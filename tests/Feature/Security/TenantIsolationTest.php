<?php

namespace Tests\Feature\Security;

use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private Tenant $otherTenant;
    private User $otherOwner;
    private Site $otherSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otherTenant = Tenant::factory()->create();
        $this->otherOwner = User::factory()->owner()->create(['tenant_id' => $this->otherTenant->id]);

        // Create a site for the other tenant
        $this->setTenantScope($this->otherOwner);
        $this->otherSite = Site::factory()->create(['tenant_id' => $this->otherTenant->id]);
        Page::factory()->create(['site_id' => $this->otherSite->id]);
    }

    public function test_user_cannot_list_other_tenants_sites(): void
    {
        $this->setTenantScope($this->owner);
        $mySite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsOwner()
            ->getJson('/api/v1/sites', $this->apiHeaders());

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mySite->id));
        $this->assertFalse($ids->contains($this->otherSite->id));
    }

    public function test_user_cannot_view_other_tenants_site(): void
    {
        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->otherSite->id}", $this->apiHeaders());

        $response->assertNotFound();
    }

    public function test_user_cannot_update_other_tenants_site(): void
    {
        $response = $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->otherSite->id}", [
                'name' => 'Hacked Name',
            ], $this->apiHeaders());

        $response->assertNotFound();
    }

    public function test_user_cannot_delete_other_tenants_site(): void
    {
        $response = $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->otherSite->id}", [], $this->apiHeaders());

        $response->assertNotFound();
    }

    public function test_user_cannot_access_other_tenants_pages(): void
    {
        $this->setTenantScope($this->owner);
        $mySite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->otherSite->id}/pages", $this->apiHeaders());

        $response->assertNotFound();
    }

    public function test_rls_prevents_cross_tenant_query_at_db_level(): void
    {
        // Set tenant scope to our tenant
        $this->setTenantScope($this->owner);

        // Query sites — should only return our tenant's sites
        $sites = Site::all();
        $this->assertFalse($sites->contains('id', $this->otherSite->id));
    }

    public function test_tenant_scope_middleware_sets_pg_variable(): void
    {
        $this->setTenantScope($this->owner);
        $mySite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAsOwner()
            ->getJson('/api/v1/sites', $this->apiHeaders());

        $response->assertOk();

        // The middleware should have set the PG variable allowing access to our data
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mySite->id));
    }
}
