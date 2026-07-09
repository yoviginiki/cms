<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class PageTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/sites/{$this->site->id}/pages{$suffix}";
    }

    public function test_can_list_pages(): void
    {
        Page::factory()->count(2)->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()->getJson($this->url(), $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_page(): void
    {
        $this->actingAsOwner()->postJson($this->url(), [
            'title' => 'My New Page',
        ], $this->apiHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'My New Page');

        $this->assertDatabaseHas('pages', ['site_id' => $this->site->id, 'title' => 'My New Page']);
    }

    public function test_can_update_page(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'title' => 'Old']);

        $this->actingAsOwner()->putJson("{$this->url()}/{$page->id}", [
            'title' => 'Updated',
        ], $this->apiHeaders())->assertOk();

        $this->assertSame('Updated', $page->fresh()->title);
    }

    public function test_renaming_a_published_page_slug_writes_a_301(): void
    {
        // FIX-B7b
        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'old-slug']);

        $this->actingAsOwner()->putJson("{$this->url()}/{$page->id}", [
            'slug' => 'new-slug',
        ], $this->apiHeaders())->assertOk();

        $this->assertDatabaseHas('redirects', [
            'site_id' => $this->site->id,
            'source_path' => '/old-slug/',
            'target_url' => '/new-slug/',
            'status_code' => 301,
        ]);
    }

    public function test_can_delete_page(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()->deleteJson("{$this->url()}/{$page->id}", [], $this->apiHeaders())
            ->assertNoContent();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_auto_generates_unique_slug(): void
    {
        Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'about']);

        $this->actingAsOwner()->postJson($this->url(), [
            'title' => 'About',
        ], $this->apiHeaders())->assertStatus(201);

        // two pages titled/slugged "about" must not collide
        $this->assertSame(2, Page::where('site_id', $this->site->id)->where('slug', 'like', 'about%')->count());
    }

    public function test_can_reorder_pages(): void
    {
        $a = Page::factory()->create(['site_id' => $this->site->id, 'sort_order' => 0]);
        $b = Page::factory()->create(['site_id' => $this->site->id, 'sort_order' => 1]);

        $this->actingAsOwner()->postJson("{$this->url()}/reorder", [
            'items' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 1],
            ],
        ], $this->apiHeaders())->assertOk();

        $this->assertTrue($b->fresh()->sort_order < $a->fresh()->sort_order);
    }

    public function test_editor_can_create_page(): void
    {
        $this->actingAsEditor()->postJson($this->url(), [
            'title' => 'Editor Page',
        ], $this->apiHeaders())->assertStatus(201);
    }

    public function test_editor_cannot_delete_page(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsEditor()->deleteJson("{$this->url()}/{$page->id}", [], $this->apiHeaders())
            ->assertForbidden();
    }
}
