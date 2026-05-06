<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use Tests\TestCase;

class VersionTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
    }

    private function makeVersion(array $attrs = []): PageVersion
    {
        return PageVersion::create(array_merge([
            'page_id' => $this->page->id,
            'blocks_snapshot' => [['type' => 'text', 'order' => 0, 'data' => [], 'style' => [], 'children' => []]],
            'seo_snapshot' => [],
            'published_by' => $this->owner->id,
            'published_at' => now(),
            'version_number' => 1,
        ], $attrs));
    }

    public function test_can_list_page_versions(): void
    {
        $this->makeVersion(['version_number' => 1]);
        $this->makeVersion(['version_number' => 2]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_show_page_version(): void
    {
        $version = $this->makeVersion();

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$version->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $version->id);
    }

    public function test_can_restore_page_version(): void
    {
        $version = $this->makeVersion([
            'blocks_snapshot' => [['type' => 'hero', 'order' => 0, 'data' => [], 'style' => [], 'children' => []]],
        ]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$version->id}/restore")
            ->assertStatus(200);
    }

    public function test_can_list_post_versions(): void
    {
        $post = \App\Models\Post::factory()->published()->create(['site_id' => $this->site->id]);
        PageVersion::create([
            'post_id' => $post->id,
            'blocks_snapshot' => [],
            'seo_snapshot' => [],
            'published_by' => $this->owner->id,
            'published_at' => now(),
            'version_number' => 1,
        ]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}/versions")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_editor_can_restore_version(): void
    {
        // Editors have 'update' permission on pages (PagePolicy), so restore is allowed.
        $version = $this->makeVersion();

        $this->actingAsEditor()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/versions/{$version->id}/restore")
            ->assertStatus(200);
    }
}
