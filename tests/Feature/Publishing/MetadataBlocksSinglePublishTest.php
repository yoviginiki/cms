<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use Tests\TestCase;

/**
 * F17 residual (audit 2026-09-22) — the editor saves metadata, then blocks.
 * Before: the metadata PUT started a build, the blocks PUT found it running
 * and the published page mixed new metadata with old blocks until a
 * follow-up. Now: metadata with defer_publish only flags the post; the
 * blocks save triggers ONE deployment that carries both changes.
 */
class MetadataBlocksSinglePublishTest extends TestCase
{
    public function test_metadata_then_blocks_ship_in_one_deployment(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $site->update(['settings' => ['homepage_id' => Page::where('site_id', $site->id)->value('id'), 'auto_publish' => true]]);
        $post = Post::factory()->published()->create(['site_id' => $site->id, 'slug' => 'story', 'title' => 'Old title']);
        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
        $before = Deployment::where('site_id', $site->id)->count();

        // 1. metadata with defer_publish → no deployment, durable flag
        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/posts/{$post->id}", [
            'title' => 'New title', 'defer_publish' => true,
        ], $this->apiHeaders())->assertOk();
        $this->assertSame($before, Deployment::where('site_id', $site->id)->count());
        $this->assertTrue($post->fresh()->needs_republish);

        // 2. blocks → exactly one deployment with BOTH changes
        $version = $this->actingAsOwner()->getJson("/api/v1/sites/{$site->id}/posts/{$post->id}/blocks", $this->apiHeaders())->json('version');
        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/posts/{$post->id}/blocks", [
            'expected_version' => $version,
            'blocks' => [['type' => 'text', 'order' => 0, 'data' => ['content' => '<p>NEW BODY</p>']]],
        ], $this->apiHeaders())->assertOk();

        $this->assertSame($before + 1, Deployment::where('site_id', $site->id)->count());
        $dep = Deployment::where('site_id', $site->id)->latest('created_at')->first();
        $this->assertSame('live', $dep->status);
        $path = collect($dep->metadata['built'])->firstWhere('id', $post->id)['path'];
        $html = file_get_contents(config('publishing.public_path') . "/{$site->slug}/{$path}");
        $this->assertStringContainsString('New title', $html);
        $this->assertStringContainsString('NEW BODY', $html);
        $this->assertFalse($post->fresh()->needs_republish);
    }

    public function test_without_defer_the_metadata_update_still_publishes(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $site->update(['settings' => ['homepage_id' => Page::where('site_id', $site->id)->value('id'), 'auto_publish' => true]]);
        $post = Post::factory()->published()->create(['site_id' => $site->id, 'slug' => 'story2']);
        $before = Deployment::where('site_id', $site->id)->count();

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}/posts/{$post->id}", ['title' => 'Plain'], $this->apiHeaders())->assertOk();
        $this->assertSame($before + 1, Deployment::where('site_id', $site->id)->count());
    }
}
