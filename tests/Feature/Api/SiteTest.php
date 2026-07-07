<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SiteTest extends TestCase
{
    public function test_can_list_sites(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_create_site(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_update_site(): void
    {
        $this->markTestIncomplete();
    }

    public function test_changing_homepage_flags_it_stale(): void
    {
        // FIX-B7a: changing the homepage must mark it for republish so the
        // site root is rebuilt (old index.html mustn't stay live).
        $this->setTenantScope($this->owner);
        $site = \App\Models\Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = \App\Models\Page::factory()->create(['site_id' => $site->id, 'needs_republish' => false]);

        $response = $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => ['homepage_type' => 'page', 'homepage_id' => $page->id],
        ], $this->apiHeaders());

        $response->assertOk();
        $this->assertTrue($page->fresh()->needs_republish, 'new homepage was not flagged stale');
    }

    public function test_can_delete_site(): void
    {
        $this->markTestIncomplete();
    }

    public function test_editor_cannot_create_site(): void
    {
        $this->markTestIncomplete();
    }

    public function test_editor_cannot_delete_site(): void
    {
        $this->markTestIncomplete();
    }

    public function test_creates_default_theme_on_site_creation(): void
    {
        $this->markTestIncomplete();
    }

    public function test_auto_generates_slug(): void
    {
        $this->markTestIncomplete();
    }
}
