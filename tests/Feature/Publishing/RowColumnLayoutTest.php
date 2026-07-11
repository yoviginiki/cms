<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * P5 Layout Logic — 12-grid column widths (`col_spans`) and per-breakpoint
 * mobile stack order (`stack_order`) on the row block. col_spans overrides the
 * legacy `layout` preset; stack_order emits `order` rules inside the row's
 * ≤767px media query.
 */
class RowColumnLayoutTest extends TestCase
{
    /** Build section > row(colCount) with text in each column; return the HTML. */
    private function renderRow(array $rowData, int $colCount = 2): string
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);

        $section = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => [],
        ]);
        $row = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $section->id, 'type' => 'row', 'level' => 'row', 'order' => 0,
            'data' => $rowData,
        ]);
        for ($i = 0; $i < $colCount; $i++) {
            $col = Block::create([
                'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
                'parent_block_id' => $row->id, 'type' => 'column', 'level' => 'column', 'order' => $i,
                'data' => [],
            ]);
            Block::create([
                'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
                'parent_block_id' => $col->id, 'type' => 'text', 'level' => 'module', 'order' => 0,
                'data' => ['content' => "COL-{$i}-CONTENT"],
            ]);
        }

        return app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
    }

    public function test_col_spans_override_the_layout_preset(): void
    {
        $html = $this->renderRow(['layout' => '1/2+1/2', 'col_spans' => [4, 8]], 2);
        // the row's own grid uses the explicit spans, not the 1fr 1fr preset
        $this->assertStringContainsString('grid-template-columns:4fr 8fr', $html);
    }

    public function test_missing_col_spans_falls_back_to_preset(): void
    {
        $html = $this->renderRow(['layout' => '1/3+2/3'], 2);
        $this->assertStringContainsString('grid-template-columns:1fr 2fr', $html);
    }

    public function test_invalid_col_spans_are_ignored(): void
    {
        // out-of-range span → the whole array is rejected, preset stands
        $html = $this->renderRow(['layout' => '1/2+1/2', 'col_spans' => [99, 8]], 2);
        $this->assertStringContainsString('grid-template-columns:1fr 1fr', $html);
        $this->assertStringNotContainsString('99fr', $html);
    }

    public function test_stack_order_emits_mobile_order_rules(): void
    {
        // display order [2,0,1] → origIndex 0→pos1, 1→pos2, 2→pos0
        $html = $this->renderRow(['layout' => '1/3+1/3+1/3', 'stack_order' => [2, 0, 1]], 3);
        $this->assertStringContainsString('@media(max-width:767px)', $html);
        $this->assertStringContainsString('*:nth-child(1){order:1;}', $html);
        $this->assertStringContainsString('*:nth-child(2){order:2;}', $html);
        $this->assertStringContainsString('*:nth-child(3){order:0;}', $html);
    }

    public function test_no_stack_order_means_no_order_rules(): void
    {
        $html = $this->renderRow(['layout' => '1/2+1/2'], 2);
        $this->assertStringNotContainsString('{order:', $html);
    }

    public function test_malformed_stack_order_is_ignored(): void
    {
        // duplicate index → invalid permutation → no rules emitted
        $html = $this->renderRow(['layout' => '1/3+1/3+1/3', 'stack_order' => [0, 0, 1]], 3);
        $this->assertStringNotContainsString('{order:', $html);
    }
}
