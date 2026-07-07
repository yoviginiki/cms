<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PageTest extends TestCase
{
    public function test_can_list_pages(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_create_page(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_update_page(): void
    {
        $this->markTestIncomplete();
    }

    public function test_renaming_a_published_page_slug_writes_a_301(): void
    {
        // FIX-B7b
        $this->setTenantScope($this->owner);
        $site = \App\Models\Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = \App\Models\Page::factory()->published()->create(['site_id' => $site->id, 'slug' => 'old-slug']);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/pages/{$page->id}", [
            'slug' => 'new-slug',
        ], $this->apiHeaders())->assertOk();

        $this->assertDatabaseHas('redirects', [
            'site_id' => $site->id,
            'source_path' => '/old-slug/',
            'target_url' => '/new-slug/',
            'status_code' => 301,
        ]);
    }

    public function test_can_delete_page(): void
    {
        $this->markTestIncomplete();
    }

    public function test_auto_generates_unique_slug(): void
    {
        $this->markTestIncomplete();
    }

    public function test_can_reorder_pages(): void
    {
        $this->markTestIncomplete();
    }

    public function test_editor_can_create_page(): void
    {
        $this->markTestIncomplete();
    }

    public function test_editor_cannot_delete_page(): void
    {
        $this->markTestIncomplete();
    }
}
