<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
    }

    public function test_can_list_sites(): void
    {
        Site::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->getJson('/api/v1/sites', $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_site(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/sites', [
            'name' => 'My Site',
        ], $this->apiHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'My Site');
    }

    public function test_can_update_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old']);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'name' => 'New Name',
        ], $this->apiHeaders())->assertOk();

        $this->assertSame('New Name', $site->fresh()->name);
    }

    public function test_changing_homepage_flags_it_stale(): void
    {
        // FIX-B7a: changing the homepage must mark it for republish so the
        // site root is rebuilt (old index.html mustn't stay live).
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id, 'needs_republish' => false]);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => ['homepage_type' => 'page', 'homepage_id' => $page->id],
        ], $this->apiHeaders())->assertOk();

        $this->assertTrue($page->fresh()->needs_republish, 'new homepage was not flagged stale');
    }

    public function test_can_delete_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$site->id}", [], $this->apiHeaders())
            ->assertNoContent();
    }

    public function test_editor_cannot_create_site(): void
    {
        $this->actingAsEditor()->postJson('/api/v1/sites', [
            'name' => 'Nope',
        ], $this->apiHeaders())->assertForbidden();
    }

    public function test_editor_cannot_delete_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        // delete is owner-only
        $this->actingAsAdmin()->deleteJson("/api/v1/sites/{$site->id}", [], $this->apiHeaders())
            ->assertForbidden();
    }

    public function test_creates_default_theme_on_site_creation(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/v1/sites', [
            'name' => 'Themed Site',
        ], $this->apiHeaders())->assertStatus(201);

        $siteId = $response->json('data.id');
        $this->assertDatabaseHas('themes', ['site_id' => $siteId]);
    }

    public function test_auto_generates_slug(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/v1/sites', [
            'name' => 'Auto Slug Site',
        ], $this->apiHeaders())->assertStatus(201);

        $this->assertNotEmpty($response->json('data.slug'));
    }
}
