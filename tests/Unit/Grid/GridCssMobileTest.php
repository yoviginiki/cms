<?php

namespace Tests\Unit\Grid;

use App\Domain\Grid\Services\GridCssGenerator;
use App\Models\Grid;
use App\Models\Site;
use Tests\TestCase;

/**
 * Guards the SYSTEMIC mobile defaults baked into grid CSS: every grid page must
 * be single-column on phones and its items must be allowed to shrink, so a wide
 * descendant can never blow the page out horizontally at 390px. Regressing this
 * is what forced per-page mobile fixes across the vioiv migration.
 */
class GridCssMobileTest extends TestCase
{
    private function makeGrid(array $attrs = []): Grid
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        return Grid::create(array_merge([
            'site_id' => $site->id,
            'name' => 'Test Grid',
            'slug' => 'test-grid-' . uniqid(),
            'col_tracks' => '2fr 1fr',
            'row_tracks' => 'auto',
            'areas' => '"main aside"',
            'container_width' => '1200px',
            'gap_x' => '24px',
            'gap_y' => '24px',
            'is_preset' => false,
        ], $attrs));
    }

    public function test_grid_items_may_shrink_below_content_size(): void
    {
        $css = (new GridCssGenerator())->generate($this->makeGrid());

        // Without min-width:0 a single nowrap child forces the track past the
        // viewport — this is the #1 mobile-overflow bug on grid pages.
        $this->assertStringContainsString('.site-grid > * { min-width: 0; }', $css);
    }

    public function test_multi_column_grid_collapses_to_single_column_on_mobile_by_default(): void
    {
        $css = (new GridCssGenerator())->generate($this->makeGrid());

        $this->assertStringContainsString('@media (max-width: 768px)', $css);
        $this->assertStringContainsString('.site-grid { grid-template-columns: 1fr; }', $css);
    }

    public function test_mobile_collapse_fires_without_any_mobile_order_configured(): void
    {
        // Previously the 1fr collapse only appeared when a position had a
        // mobile_order — leaving most imported grids multi-column on phones.
        $grid = $this->makeGrid();
        $this->assertEmpty($grid->breakpoints_json['mobile'] ?? []);

        $css = (new GridCssGenerator())->generate($grid);
        $this->assertStringContainsString('.site-grid { grid-template-columns: 1fr; }', $css);
    }

    public function test_explicit_mobile_breakpoint_still_wins(): void
    {
        $grid = $this->makeGrid([
            'breakpoints_json' => ['mobile' => ['col_tracks' => '1fr 1fr']],
        ]);

        $css = (new GridCssGenerator())->generate($grid);

        // The author's explicit mobile layout is respected (2-col), and the
        // blanket 1fr fallback is NOT also emitted.
        $this->assertStringContainsString('grid-template-columns: 1fr 1fr;', $css);
        $this->assertStringNotContainsString('.site-grid { grid-template-columns: 1fr; }', $css);
    }
}
