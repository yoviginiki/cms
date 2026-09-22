<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Canvas editor save goes through the SAME sync endpoint + SyncBlocksRequest as
 * the block editor. The canvas tree is Section → Module (freeform children, no
 * Row/Column), which the 4-level hierarchy validator must accept for a canvas
 * section — this used to fail with "Module cannot be inside Section".
 */
class CanvasSyncRequestTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** Exactly what lib/canvasAdapter.ts canvasToBlocks() emits. */
    private function canvasTree(): array
    {
        return [[
            'id' => '11111111-1111-4111-8111-111111111111',
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => ['canvas' => ['height' => 480, 'bleed' => false, 'background' => '']],
            'style' => [],
            'children' => [
                [
                    'id' => '22222222-2222-4222-8222-222222222222',
                    'type' => 'text', 'order' => 0,
                    'data' => ['content' => '<p>Hello</p>'],
                    'style' => ['layout' => ['position' => 'absolute', 'x' => 40, 'y' => 40, 'width' => '260px', 'height' => '120px', 'rotation' => 0, 'zIndex' => 1, 'locked' => false, 'opacity' => 0.5]],
                    'children' => [],
                ],
                [
                    'id' => '33333333-3333-4333-8333-333333333333',
                    'type' => 'heading', 'order' => 1,
                    'data' => ['text' => 'Title', 'level' => 'h2'],
                    'style' => ['layout' => ['position' => 'absolute', 'x' => 64, 'y' => 64, 'width' => '260px', 'height' => '120px', 'rotation' => 0, 'zIndex' => 2, 'locked' => false]],
                    'children' => [],
                ],
            ],
        ]];
    }

    public function test_canvas_page_tree_saves_through_the_sync_endpoint(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'editor_mode' => 'canvas']);

        $res = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$page->id}/blocks",
            ['blocks' => $this->canvasTree()],
            $this->apiHeaders(),
        );

        $res->assertOk();
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertCount(2, $data[0]['children']);
        $this->assertSame(0.5, $data[0]['children'][0]['style']['layout']['opacity']);
    }

    public function test_canvas_post_tree_saves_through_the_sync_endpoint(): void
    {
        $post = Post::factory()->create(['site_id' => $this->site->id, 'editor_mode' => 'canvas']);

        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/posts/{$post->id}/blocks",
            ['blocks' => $this->canvasTree()],
            $this->apiHeaders(),
        )->assertOk();
    }

    public function test_plain_section_still_rejects_a_module_child(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);
        $tree = $this->canvasTree();
        unset($tree[0]['data']['canvas']);   // an ordinary (block-editor) section

        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$page->id}/blocks",
            ['blocks' => $tree],
            $this->apiHeaders(),
        )->assertStatus(422)
          ->assertJsonFragment(['message' => 'Module cannot be inside Section. Section can only contain: Row. (and 1 more error)']);
    }
}
