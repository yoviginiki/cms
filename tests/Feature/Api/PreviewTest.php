<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class PreviewTest extends TestCase
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

    public function test_can_preview_page(): void
    {
        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/preview")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_can_preview_post(): void
    {
        $post = Post::factory()->published()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/posts/{$post->id}/preview")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_can_render_block(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/blocks/render", [
                'type' => 'paragraph',
                'data' => ['content' => 'Hello world'],
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['html']);
    }

    public function test_can_create_preview_token(): void
    {
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/page/{$this->page->id}/preview-token")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'url', 'expires_at']]);
    }

    public function test_unauthenticated_cannot_preview(): void
    {
        $this->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/preview")
            ->assertStatus(401);
    }
}
