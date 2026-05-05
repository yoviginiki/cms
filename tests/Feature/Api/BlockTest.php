<?php

namespace Tests\Feature\Api;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

class BlockTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->create(['site_id' => $this->site->id]);
    }

    public function test_can_sync_blocks(): void
    {
        $blocks = [
            ['type' => 'hero', 'data' => ['title' => 'Welcome', 'subtitle' => 'Hello'], 'order' => 0],
            ['type' => 'text', 'data' => ['content' => '<p>Some text</p>'], 'order' => 1],
        ];

        $response = $this->actingAsOwner()
            ->putJson(
                "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
                ['blocks' => $blocks],
                $this->apiHeaders(),
            );

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals('hero', $data[0]['type']);
        $this->assertEquals('Welcome', $data[0]['data']['title']);
        $this->assertEquals('text', $data[1]['type']);
    }

    public function test_sync_replaces_all_blocks(): void
    {
        // First sync
        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'Old'], 'order' => 0],
                ['type' => 'text', 'data' => ['content' => 'Old text'], 'order' => 1],
            ]],
            $this->apiHeaders(),
        );

        // Second sync — should replace all
        $response = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'New heading', 'level' => 'h1'], 'order' => 0],
            ]],
            $this->apiHeaders(),
        );

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('heading', $data[0]['type']);

        // Verify old blocks are gone
        $this->assertEquals(1, Block::where('blockable_id', $this->page->id)->count());
    }

    public function test_sync_handles_nested_children(): void
    {
        $blocks = [
            [
                'type' => 'columns',
                'data' => ['column_count' => 2, 'gap' => 'medium'],
                'order' => 0,
                'children' => [
                    ['type' => 'text', 'data' => ['content' => 'Left'], 'order' => 0],
                    ['type' => 'text', 'data' => ['content' => 'Right'], 'order' => 1],
                ],
            ],
        ];

        $response = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => $blocks],
            $this->apiHeaders(),
        );

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('columns', $data[0]['type']);
        $this->assertCount(2, $data[0]['children']);
        $this->assertEquals('Left', $data[0]['children'][0]['data']['content']);
    }

    public function test_rejects_too_many_blocks(): void
    {
        $blocks = [];
        for ($i = 0; $i < 501; $i++) {
            $blocks[] = ['type' => 'text', 'data' => ['content' => "Block $i"], 'order' => $i];
        }

        $response = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => $blocks],
            $this->apiHeaders(),
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('blocks');
    }

    public function test_rejects_too_deep_nesting(): void
    {
        $blocks = [
            [
                'type' => 'columns', 'data' => ['column_count' => 2], 'order' => 0,
                'children' => [
                    [
                        'type' => 'columns', 'data' => ['column_count' => 2], 'order' => 0,
                        'children' => [
                            [
                                'type' => 'columns', 'data' => ['column_count' => 2], 'order' => 0,
                                'children' => [
                                    ['type' => 'text', 'data' => ['content' => 'Too deep'], 'order' => 0],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => $blocks],
            $this->apiHeaders(),
        );

        $response->assertUnprocessable();
    }

    public function test_get_block_tree(): void
    {
        // Sync some blocks first
        $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'Hello'], 'order' => 0],
                ['type' => 'text', 'data' => ['content' => 'World'], 'order' => 1],
            ]],
            $this->apiHeaders(),
        );

        $response = $this->actingAsOwner()
            ->getJson(
                "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
                $this->apiHeaders(),
            );

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_editor_can_sync_blocks(): void
    {
        $response = $this->actingAsEditor()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => [['type' => 'text', 'data' => ['content' => 'Editor'], 'order' => 0]]],
            $this->apiHeaders(),
        );

        $response->assertOk();
    }
}
