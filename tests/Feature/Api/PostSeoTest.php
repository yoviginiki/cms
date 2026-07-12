<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Track F2 — post SEO controls through the API: author assignment,
 * partial seo_meta merge (never clobbers canvas config), validation.
 */
class PostSeoTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(Post $post): string
    {
        return "/api/v1/sites/{$this->site->id}/posts/{$post->id}";
    }

    public function test_author_can_be_set_via_api(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => null]);

        $this->actingAsOwner()->putJson($this->url($post), [
            'author_id' => $this->owner->id,
        ], $this->apiHeaders())->assertOk();

        $this->assertEquals($this->owner->id, $post->fresh()->author_id);
    }

    public function test_partial_seo_meta_patch_merges_instead_of_replacing(): void
    {
        $post = Post::factory()->create([
            'site_id' => $this->site->id, 'category_id' => null,
            'seo_meta' => ['canvas' => ['page_type' => 'single', 'width' => 1200], 'title' => 'Old'],
        ]);

        $this->actingAsOwner()->putJson($this->url($post), [
            'seo_meta' => ['title' => 'New SEO Title', 'no_index' => true, 'canonical' => 'https://example.com/x'],
        ], $this->apiHeaders())->assertOk();

        $meta = $post->fresh()->seo_meta;
        $this->assertSame('New SEO Title', $meta['title']);
        $this->assertTrue((bool) $meta['no_index']);
        $this->assertEquals(['page_type' => 'single', 'width' => 1200], $meta['canvas']);
    }

    public function test_invalid_canonical_is_rejected(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id, 'category_id' => null]);

        $this->actingAsOwner()->putJson($this->url($post), [
            'seo_meta' => ['canonical' => 'not-a-url'],
        ], $this->apiHeaders())->assertStatus(422);
    }
}
