<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class DiffTest extends TestCase
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

    public function test_can_diff_page(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/diff")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_diff_post(): void
    {
        $post = Post::factory()->published()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}/diff")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_editor_can_diff_page(): void
    {
        $this->actingAsEditor()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/diff")
            ->assertStatus(200);
    }

    public function test_unauthenticated_cannot_diff(): void
    {
        $this->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/diff")
            ->assertStatus(401);
    }
}
