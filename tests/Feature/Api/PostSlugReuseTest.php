<?php

namespace Tests\Feature\Api;

use App\Domain\Posts\Services\PostService;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * F28 (audit 2026-09-22) — a soft-deleted post keeps its slug in the unique
 * index; a new post with the same title must get a predictable suffix (not a
 * 500), restore keeps uniqueness, and a lost race on the index is retried.
 */
class PostSlugReuseTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_delete_then_create_with_the_same_title_gets_a_suffix(): void
    {
        $first = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/posts", ['title' => 'Hello World'], $this->apiHeaders())
            ->assertStatus(201)->json('data');
        $this->assertSame('hello-world', $first['slug']);

        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$this->site->id}/posts/{$first['id']}", [], $this->apiHeaders());
        $this->assertNotNull(Post::withTrashed()->find($first['id'])->deleted_at);

        $second = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/posts", ['title' => 'Hello World'], $this->apiHeaders())
            ->assertStatus(201)->json('data');
        $this->assertSame('hello-world-1', $second['slug']);

        // restoring the first keeps both unique
        Post::withTrashed()->find($first['id'])->restore();
        $this->assertSame(2, Post::where('site_id', $this->site->id)->count());
        $this->assertSame(2, Post::where('site_id', $this->site->id)->distinct('slug')->count('slug'));
    }

    public function test_a_lost_race_on_the_unique_index_is_retried_with_the_next_suffix(): void
    {
        $svc = app(PostService::class);
        // Simulate "someone inserted the same slug between our check and insert":
        // pre-create the row the generator would pick, bypassing the generator.
        Post::create(['site_id' => $this->site->id, 'title' => 'Race', 'slug' => 'race', 'status' => 'draft']);
        $post = $svc->createPost(['title' => 'Race'], $this->site);
        $this->assertSame('race-1', $post->slug);
    }
}
