<?php

namespace Tests\Feature\Auth;

use App\Models\Site;
use App\Models\User;
use Tests\TestCase;

/**
 * Per-site access: users created directly by an admin, restricted to a set
 * of sites with a role per site.
 */
class SiteAccessTest extends TestCase
{
    private Site $siteA;
    private Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->siteA = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->siteB = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createRestricted(string $siteRole = 'editor', ?Site $site = null): User
    {
        $response = $this->actingAsOwner()->postJson('/api/v1/users', [
            'name' => 'Okito',
            'email' => 'okito@example.com',
            'password' => 'secret-pass-1',
            'role' => 'editor',
            'restricted_to_sites' => true,
            'sites' => [['site_id' => ($site ?? $this->siteA)->id, 'role' => $siteRole]],
        ], $this->apiHeaders());
        $response->assertStatus(201);

        return User::where('email', 'okito@example.com')->firstOrFail();
    }

    public function test_owner_creates_an_active_user_directly(): void
    {
        $user = $this->createRestricted();

        $this->assertNull($user->invitation_token);
        $this->assertTrue($user->restricted_to_sites);
        $this->assertSame('editor', $user->role);
        $this->assertTrue(\Hash::check('secret-pass-1', $user->password));
        $this->assertDatabaseHas('site_user', ['user_id' => $user->id, 'site_id' => $this->siteA->id, 'role' => 'editor']);
    }

    public function test_restricted_user_only_lists_and_opens_granted_sites(): void
    {
        $user = $this->createRestricted();
        $this->actingAs($user, 'sanctum');

        $ids = collect($this->getJson('/api/v1/sites', $this->apiHeaders())->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$this->siteA->id], $ids->all());

        $this->getJson("/api/v1/sites/{$this->siteA->id}", $this->apiHeaders())->assertOk();
        $this->getJson("/api/v1/sites/{$this->siteB->id}", $this->apiHeaders())->assertNotFound();
        $this->getJson("/api/v1/sites/{$this->siteB->id}/pages", $this->apiHeaders())->assertNotFound();
    }

    public function test_per_site_role_applies_on_that_site(): void
    {
        // viewer on A: may look, may not publish
        $user = $this->createRestricted('viewer');
        $this->assertSame('viewer', $user->role);
        $user->actOnSite($this->siteA->id);
        $this->assertFalse($user->hasMinimumRole('editor'));

        // admin on A (owner may grant it): site admin there, still capped tenant-wide
        $user->forceDelete();
        $admin = $this->createRestricted('admin');
        $this->assertSame('editor', $admin->role);
        $this->assertFalse($admin->hasMinimumRole('admin'));
        $admin->actOnSite($this->siteA->id);
        $this->assertTrue($admin->hasMinimumRole('admin'));
    }

    public function test_restricted_user_cannot_reach_tenant_admin_screens(): void
    {
        $user = $this->createRestricted('admin');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/users', $this->apiHeaders())->assertForbidden();
    }

    public function test_admin_cannot_grant_per_site_admin(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'role' => 'editor',
            'restricted_to_sites' => true,
            'sites' => [['site_id' => $this->siteA->id, 'role' => 'admin']],
        ], $this->apiHeaders())->assertForbidden();
    }

    public function test_restricted_user_needs_at_least_one_site(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'role' => 'editor',
            'restricted_to_sites' => true, 'sites' => [],
        ], $this->apiHeaders())->assertStatus(422);
    }

    public function test_update_changes_sites_and_can_lift_the_restriction(): void
    {
        $user = $this->createRestricted();

        $this->actingAsOwner()->putJson("/api/v1/users/{$user->id}", [
            'sites' => [['site_id' => $this->siteB->id, 'role' => 'author']],
        ], $this->apiHeaders())->assertOk();
        $this->assertSame([$this->siteB->id => 'author'], $user->fresh()->siteRoles());
        $this->assertSame('author', $user->fresh()->role);

        $this->actingAsOwner()->putJson("/api/v1/users/{$user->id}", [
            'restricted_to_sites' => false, 'role' => 'editor', 'password' => 'another-pass-2',
        ], $this->apiHeaders())->assertOk();
        $fresh = $user->fresh();
        $this->assertFalse($fresh->restricted_to_sites);
        $this->assertSame([], $fresh->siteRoles());
        $this->assertTrue(\Hash::check('another-pass-2', $fresh->password));
    }

    public function test_index_reports_site_grants(): void
    {
        $user = $this->createRestricted();
        $row = collect($this->actingAsOwner()->getJson('/api/v1/users', $this->apiHeaders())->assertOk()->json('data'))
            ->firstWhere('id', $user->id);

        $this->assertTrue($row['restricted_to_sites']);
        $this->assertSame($this->siteA->id, $row['sites'][0]['site_id']);
        $this->assertSame('editor', $row['sites'][0]['role']);
    }

    public function test_cannot_delete_a_user_of_another_tenant(): void
    {
        $other = User::factory()->editor()->create(['tenant_id' => \App\Models\Tenant::factory()->create()->id]);
        $this->actingAsOwner()->deleteJson("/api/v1/users/{$other->id}", [], $this->apiHeaders())->assertNotFound();
    }

    public function test_recreating_a_removed_user_restores_the_account(): void
    {
        $user = $this->createRestricted();
        $user->delete();

        $again = $this->createRestricted('author', $this->siteB);
        $this->assertSame($user->id, $again->id);
        $this->assertSame([$this->siteB->id => 'author'], $again->siteRoles());
    }
}
