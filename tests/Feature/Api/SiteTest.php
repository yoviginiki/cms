<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
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

    public function test_can_resolve_site_by_slug(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'my-slugged-site']);

        $this->actingAsOwner()->getJson('/api/v1/sites/my-slugged-site', $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $site->id);
    }

    public function test_slug_binding_stays_tenant_scoped(): void
    {
        $other = Tenant::factory()->create();
        $otherOwner = \App\Models\User::factory()->owner()->create(['tenant_id' => $other->id]);
        $this->setTenantScope($otherOwner);
        Site::factory()->create(['tenant_id' => $other->id, 'slug' => 'foreign-site']);
        $this->setTenantScope($this->owner);

        $this->actingAsOwner()->getJson('/api/v1/sites/foreign-site', $this->apiHeaders())
            ->assertNotFound();
    }

    public function test_can_update_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old']);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'name' => 'New Name',
        ], $this->apiHeaders())->assertOk();

        $this->assertSame('New Name', $site->fresh()->name);
    }

    public function test_settings_tabs_keys_without_a_rule_are_saved(): void
    {
        // Branding / Languages / Custom Code keys have no per-key rule; Laravel's
        // excludeUnvalidatedArrayKeys used to drop them silently (200, nothing saved).
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => [
            'stale' => ['flag' => true, 'reason' => 'x'], 'custom_fonts' => [['family' => 'Kept']],
        ]]);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", ['settings' => [
            'footer_text' => 'Hello', 'logo_url' => '/logo.png', 'languages' => ['en'],
            'google_analytics_id' => 'G-TEST', 'auto_publish' => false,
            // SPA echoes server-owned keys back from its cache — must not overwrite
            'stale' => null, 'custom_fonts' => [],
        ]], $this->apiHeaders())->assertOk();

        $settings = $site->fresh()->settings;
        $this->assertSame('Hello', $settings['footer_text']);
        $this->assertSame('/logo.png', $settings['logo_url']);
        $this->assertSame(['en'], $settings['languages']);
        $this->assertSame('G-TEST', $settings['google_analytics_id']);
        $this->assertFalse($settings['auto_publish']);
        $this->assertTrue($settings['stale']['flag']);
        $this->assertSame([['family' => 'Kept']], $settings['custom_fonts']);
    }

    public function test_ruled_settings_keys_are_still_validated(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", ['settings' => [
            'deploy_slug' => 'Bad Folder!',
        ]], $this->apiHeaders())->assertStatus(422)->assertJsonValidationErrors(['settings.deploy_slug']);
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
