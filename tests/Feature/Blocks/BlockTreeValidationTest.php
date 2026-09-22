<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Exceptions\InvalidBlockTreeException;
use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * F14 (audit 2026-09-22) — the central write service validates every node
 * against its registered definition before any mutation: unknown types and
 * format violations are refused with a field path; valid trees round-trip.
 */
class BlockTreeValidationTest extends TestCase
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

    private function putTree(array $blocks)
    {
        return $this->actingAsOwner()->putJson(
            "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks",
            ['blocks' => $blocks, 'overwrite' => true],
            $this->apiHeaders(),
        );
    }

    public function test_unknown_and_malformed_nodes_are_refused_before_any_write(): void
    {
        $this->putTree([['type' => 'not-a-block', 'order' => 0, 'data' => []]])
            ->assertStatus(422)->assertJsonValidationErrors(['blocks.0.type']);

        // nested: a hero inside a section with an invalid bg_type and an image with a non-uuid asset id
        $this->putTree([[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
            'children' => [[
                'type' => 'row', 'level' => 'row', 'order' => 0, 'data' => [],
                'children' => [[
                    'type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [],
                    'children' => [
                        ['type' => 'hero', 'level' => 'module', 'order' => 0, 'data' => ['title' => 'x', 'bg_type' => 'video-of-doom']],
                        ['type' => 'image', 'level' => 'module', 'order' => 1, 'data' => ['asset_id' => 'not-a-uuid', 'url' => 'javascript:alert(1)']],
                    ],
                ]],
            ]],
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'blocks.0.children.0.children.0.children.0.data.bg_type',
            'blocks.0.children.0.children.0.children.1.data.asset_id',
            'blocks.0.children.0.children.0.children.1.data.url',
        ]);

        $this->assertSame(0, Block::where('blockable_id', $this->page->id)->count());
        $this->assertSame(0, (int) $this->page->fresh()->content_revision);
    }

    public function test_service_refuses_invalid_trees_from_programmatic_callers_too(): void
    {
        $svc = app(BlockService::class);
        try {
            $svc->syncBlocks($this->page, [['type' => 'ghost-type', 'order' => 0, 'data' => []]]);
            $this->fail('expected InvalidBlockTreeException');
        } catch (InvalidBlockTreeException $e) {
            $this->assertArrayHasKey('blocks.0.type', $e->errors);
        }
        // trusted importers skip field rules, never the type check
        try {
            $svc->syncBlocks($this->page, [['type' => 'ghost-type', 'order' => 0, 'data' => []]], null, trusted: true);
            $this->fail('expected InvalidBlockTreeException');
        } catch (InvalidBlockTreeException) {
        }
        $this->assertSame(0, Block::where('blockable_id', $this->page->id)->count());
    }

    public function test_every_registered_type_accepts_its_empty_defaults_through_the_real_endpoint(): void
    {
        $types = app(BlockRegistry::class)->getAllTypes();
        $blocks = [];
        $i = 0;
        foreach ($types as $t) {
            $type = is_array($t) ? ($t['type'] ?? null) : $t;
            if (!$type) {
                continue;
            }
            $blocks[] = ['type' => $type, 'order' => $i++, 'data' => []];
        }
        $this->assertGreaterThan(50, count($blocks));
        $this->putTree($blocks)->assertOk();
        $this->assertSame(count($blocks), Block::where('blockable_id', $this->page->id)->count());
    }

    public function test_save_load_round_trip_preserves_data(): void
    {
        $tree = [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => ['anchor' => 'top'],
            'style' => ['padding' => '10px'],
            'children' => [[
                'type' => 'row', 'level' => 'row', 'order' => 0, 'data' => [],
                'children' => [[
                    'type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [],
                    'children' => [
                        ['type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => ['text' => 'Заглавие', 'level' => 'h2']],
                        ['type' => 'text', 'level' => 'module', 'order' => 1, 'data' => ['content' => '<p>Текст & „кавички“</p>']],
                    ],
                ]],
            ]],
        ]];
        $this->putTree($tree)->assertOk();
        $loaded = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks", $this->apiHeaders())->json('data');

        $col = $loaded[0]['children'][0]['children'][0];
        $this->assertSame('Заглавие', $col['children'][0]['data']['text']);
        $this->assertSame('h2', $col['children'][0]['data']['level']);
        $this->assertStringContainsString('кавички', $col['children'][1]['data']['content']);
        $this->assertSame('top', $loaded[0]['data']['anchor']);
    }
}
