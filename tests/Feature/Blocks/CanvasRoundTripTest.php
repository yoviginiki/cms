<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use Tests\TestCase;

/**
 * Canvas editor — Phase 2 integrity gate. Canvas has NO separate storage: it
 * saves the SAME block tree (section blocks carrying `canvas` settings, child
 * blocks carrying style.layout). save → reload must be byte-identical, and
 * block IDs must survive (the audit's block-id fix is what makes this hold —
 * without it every save regenerates ids and breaks versions/theme overrides).
 */
class CanvasRoundTripTest extends TestCase
{
    private function tree(): array
    {
        return [
            [
                'id' => '11111111-1111-4111-8111-111111111111',
                'type' => 'section',
                'level' => 'section',
                'order' => 0,
                'data' => ['canvas' => ['height' => 600, 'bleed' => false, 'background' => '#f5f5f5'], 'padding_top' => '2rem'],
                'style' => [],
                'children' => [
                    [
                        'id' => '22222222-2222-4222-8222-222222222222',
                        'type' => 'heading',
                        'order' => 0,
                        'data' => ['text' => 'Hello', 'level' => 'h1'],
                        'style' => ['layout' => ['position' => 'absolute', 'x' => 80, 'y' => 40, 'width' => '600px', 'height' => '90px', 'rotation' => -3, 'zIndex' => 2, 'locked' => false]],
                        'children' => [],
                    ],
                    [
                        'id' => '33333333-3333-4333-8333-333333333333',
                        'type' => 'text',
                        'order' => 1,
                        'data' => ['content' => '<p>x</p>'],
                        'style' => ['layout' => ['position' => 'absolute', 'x' => 100, 'y' => 400, 'width' => '500px', 'height' => '120px', 'rotation' => 0, 'zIndex' => 1, 'locked' => true]],
                        'children' => [],
                    ],
                ],
            ],
            [
                'id' => '44444444-4444-4444-8444-444444444444',
                'type' => 'section',
                'level' => 'section',
                'order' => 1,
                'data' => ['canvas' => ['height' => 'auto', 'bleed' => true, 'background' => '#0f172a']],
                'style' => [],
                'children' => [],
            ],
        ];
    }

    public function test_canvas_block_tree_survives_save_reload_identically(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'editor_mode' => 'canvas']);

        $svc = app(BlockService::class);
        $svc->syncBlocks($page, $this->tree());
        $reloaded = $svc->getBlockTree($page->fresh());

        // IDs preserved (audit block-id fix)
        $this->assertSame('11111111-1111-4111-8111-111111111111', $reloaded[0]['id']);
        $this->assertSame('22222222-2222-4222-8222-222222222222', $reloaded[0]['children'][0]['id']);

        // Section canvas settings preserved (JSONB is order-insensitive, so
        // assertEquals — values must match, key order is irrelevant).
        $this->assertEquals(['height' => 600, 'bleed' => false, 'background' => '#f5f5f5'], $reloaded[0]['data']['canvas']);
        $this->assertSame('auto', $reloaded[1]['data']['canvas']['height']);
        $this->assertTrue($reloaded[1]['data']['canvas']['bleed']);

        // Child freeform layout preserved exactly (by value)
        $layout = $reloaded[0]['children'][0]['style']['layout'];
        $this->assertEquals(['position' => 'absolute', 'x' => 80, 'y' => 40, 'width' => '600px', 'height' => '90px', 'rotation' => -3, 'zIndex' => 2, 'locked' => false], $layout);

        // A second identical save is a no-op (idempotent — nothing drifts)
        $svc->syncBlocks($page->fresh(), $reloaded);
        $again = $svc->getBlockTree($page->fresh());
        $this->assertEquals($reloaded, $again);
    }
}
