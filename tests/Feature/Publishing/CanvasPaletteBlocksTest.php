<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use Tests\TestCase;

/**
 * Every block the canvas palette offers (resources/admin/src/lib/canvasBlocks.ts
 * — keep PALETTE in sync) must publish inside a canvas section: the element
 * wrapper is emitted and the block renders without the "canvas element failed"
 * fallback, even from empty data.
 */
class CanvasPaletteBlocksTest extends TestCase
{
    private const PALETTE = [
        'heading', 'text', 'paragraph', 'pullquote', 'list',
        'image', 'imagecaption', 'gallery', 'linear-gallery', 'logostrip', 'beforeafter', 'video', 'audio', 'icon',
        'button', 'divider', 'shape', 'testimonial', 'stats', 'map', 'socialembed', 'html-embed',
    ];

    public function test_every_palette_block_publishes_inside_a_canvas_section(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);
        $s = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id, 'parent_block_id' => null,
            'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 'auto', 'bleed' => false, 'background' => '']],
        ]);
        foreach (self::PALETTE as $i => $type) {
            Block::create([
                'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id, 'parent_block_id' => $s->id,
                'type' => $type, 'order' => $i, 'data' => [],
                'style' => ['layout' => ['x' => 10, 'y' => 10 + $i * 120, 'width' => 300, 'height' => 100]],
            ]);
        }

        $html = app(BuildPageService::class)->build($page->fresh(), $site->fresh()->theme, $site->fresh());

        $this->assertStringNotContainsString('canvas element failed', $html);
        $this->assertSame(count(self::PALETTE), substr_count($html, 'class="cv-el"'), 'one published wrapper per palette block');
    }

    public function test_palette_list_matches_the_admin_source(): void
    {
        $src = file_get_contents(base_path('resources/admin/src/lib/canvasBlocks.ts'));
        preg_match_all("/types: \[([^\]]+)\]/", $src, $m);
        $fromTs = [];
        foreach ($m[1] as $list) {
            preg_match_all("/'([a-z0-9\-]+)'/", $list, $t);
            $fromTs = array_merge($fromTs, $t[1]);
        }
        $this->assertSame(self::PALETTE, $fromTs, 'PALETTE here must mirror CANVAS_BLOCK_GROUPS in canvasBlocks.ts');
    }
}
