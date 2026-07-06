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

    public function test_rls_forced_on_previously_unprotected_tables(): void
    {
        // Regression for STATUS.md §1 / FIX-A1a: tables that were RLS-enabled-
        // but-not-forced or had no RLS at all must now isolate at the DB level
        // even for the app's owning role. Build data as tenant A, then read as
        // tenant B and assert none of A's rows are visible.
        $this->setTenantScope($this->owner);
        $siteA = \App\Models\Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $menuA = \App\Models\Menu::create(['site_id' => $siteA->id, 'name' => 'A menu', 'slug' => 'a-menu', 'location' => 'header']);
        $magazineA = \App\Models\Magazine::create(['site_id' => $siteA->id, 'title' => 'A mag', 'slug' => 'a-mag']);
        $tagA = \App\Models\Tag::create(['site_id' => $siteA->id, 'name' => 'A tag', 'slug' => 'a-tag']);

        // Tenant B sees none of A's menus / magazines / tags
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);

        $this->assertSame(0, \App\Models\Menu::count(), 'menus leaked across tenants');
        $this->assertSame(0, \App\Models\Magazine::count(), 'magazines leaked across tenants');
        $this->assertSame(0, \App\Models\Tag::count(), 'tags leaked across tenants');

        // Back to A: rows still there for the owning tenant.
        $this->setTenantScope($this->owner);
        $this->assertSame(1, \App\Models\Menu::where('id', $menuA->id)->count());
        $this->assertSame(1, \App\Models\Magazine::where('id', $magazineA->id)->count());
        $this->assertSame(1, \App\Models\Tag::where('id', $tagA->id)->count());
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
