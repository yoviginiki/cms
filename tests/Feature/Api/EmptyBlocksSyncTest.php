<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/** A brand-new post has no blocks: Save / Publish / switching builder must not 422. */
class EmptyBlocksSyncTest extends TestCase
{
    public function test_an_empty_post_can_be_saved(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $post = Post::factory()->create(['site_id' => $site->id]);

        $version = $this->actingAsOwner()->getJson("/api/v1/sites/{$site->id}/posts/{$post->id}/blocks", $this->apiHeaders())->json('version');
        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/posts/{$post->id}/blocks", ['blocks' => [], 'expected_version' => $version], $this->apiHeaders())
            ->assertOk();
        $this->assertSame(0, $post->blocks()->count());

        // the key itself is still required
        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/posts/{$post->id}/blocks", [], $this->apiHeaders())
            ->assertStatus(422);
    }
}
