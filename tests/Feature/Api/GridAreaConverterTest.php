<?php

namespace Tests\Feature\Api;

use App\Domain\Grid\Services\GridAreaConverter;
use App\Domain\Grid\Services\GridPresetSeeder;
use App\Models\Block;
use App\Models\GlobalSection;
use App\Models\Grid;
use App\Models\GridPosition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Grid areas as blocks — stage 5: converting an EXISTING (legacy) site's grid
 * areas. Menu areas must render identically; widgets become blocks; dry run
 * saves nothing; rollback restores the legacy areas.
 */
class GridAreaConverterTest extends TestCase
{
    private Site $site;
    private Page $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        // A legacy site: no grid_areas flag, legacy presets (fixed header/footer, nav menu)
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Shop', 'settings' => ['auto_publish' => false]]);
        app(GridPresetSeeder::class)->seed($this->site);

        $this->home = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published', 'slug' => 'home', 'title' => 'Home']);
        $about = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published', 'slug' => 'about', 'title' => 'About us']);
        $this->site->update(['settings' => array_merge($this->site->settings, ['homepage_id' => $this->home->id])]);
        $menu = Menu::create(['site_id' => $this->site->id, 'name' => 'Main', 'slug' => 'main', 'location' => 'header', 'style' => ['bgColor' => '#123456']]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'About us', 'page_id' => $about->id, 'sort_order' => 0]);

        // Full Width footer: a legacy social-links + copyright widget column
        $footer = $this->fullWidth('footer');
        $footer->update(['type' => 'widget', 'config_json' => ['widgets' => [
            ['type' => 'social_links', 'title' => 'Follow', 'links' => [['name' => 'Facebook', 'url' => 'https://facebook.com/oldshop']]],
            ['type' => 'copyright', 'text' => '© 2024 Old Shop'],
            ['type' => 'tag_cloud', 'title' => 'Tags'],
        ]]]);
    }

    private function fullWidth(string $area): GridPosition
    {
        $grid = Grid::where('site_id', $this->site->id)->where('slug', 'full-width')->firstOrFail();

        return GridPosition::where('grid_id', $grid->id)->where('area_name', $area)->firstOrFail();
    }

    public function test_dry_run_reports_and_saves_nothing(): void
    {
        $result = app(GridAreaConverter::class)->convert($this->site, dryRun: true);

        $this->assertSame('widget', $this->fullWidth('footer')->fresh()->type);
        $this->assertSame('menu', $this->fullWidth('nav')->fresh()->type);
        $this->assertSame(0, GlobalSection::where('site_id', $this->site->id)->count());
        $this->assertArrayNotHasKey('grid_areas', $this->site->fresh()->settings);

        $actions = collect($result['plan'])->where('grid', 'Full Width')->pluck('action', 'area');
        $this->assertSame('convert', $actions['nav']);
        $this->assertSame('convert', $actions['footer']);
        $this->assertSame('keep', $actions['header']); // empty fixed
        $this->assertNotEmpty($result['parity']);
    }

    public function test_convert_keeps_the_menu_identical_turns_widgets_into_blocks_and_rolls_back(): void
    {
        $result = app(GridAreaConverter::class)->convert($this->site, dryRun: false);

        // Menu area: identical visible output, now a section with a site-design menu block
        $nav = $this->fullWidth('nav')->fresh();
        $this->assertSame('section', $nav->type);
        $this->assertSame('menu', $nav->config_json['legacy']['type']);
        $navBlock = Block::where('blockable_type', 'global_section')->where('blockable_id', $nav->config_json['section_id'])->firstOrFail();
        $this->assertEquals(['source' => 'system', 'location' => 'header', 'render' => 'site'], array_intersect_key($navBlock->data, array_flip(['source', 'location', 'render'])));

        // One nav section shared by every grid with the same header menu area
        $navSections = GridPosition::whereIn('grid_id', Grid::where('site_id', $this->site->id)->pluck('id'))
            ->where('area_name', 'nav')->get()->pluck('config_json.section_id')->unique();
        $this->assertCount(1, $navSections);

        // Widgets → blocks
        $footer = $this->fullWidth('footer')->fresh();
        $types = Block::where('blockable_type', 'global_section')->where('blockable_id', $footer->config_json['section_id'])->orderBy('order')->pluck('type')->all();
        $this->assertSame(['heading', 'social-links', 'copyright'], $types);
        $copyright = Block::where('blockable_id', $footer->config_json['section_id'])->where('type', 'copyright')->first();
        $this->assertSame('© {year} Old Shop', $copyright->data['text']);
        $this->assertStringContainsString('tag cloud dropped', collect($result['plan'])->first(fn ($r) => $r['grid'] === 'Full Width' && $r['area'] === 'footer')['note']);

        $home = collect($result['parity'])->firstWhere('page', 'home');
        $this->assertNotNull($home);
        $this->assertStringContainsString('About us', implode(' ', $home['only_before']) . ' ' . app(\App\Domain\Publishing\Services\BuildPageService::class)->build($this->home->fresh(), $this->site->fresh()->theme, $this->site->fresh()));

        // Rollback restores the legacy areas and removes the converter's sections
        $r = app(GridAreaConverter::class)->rollback($this->site->fresh());
        $this->assertGreaterThan(0, $r['restored']);
        $this->assertSame('menu', $this->fullWidth('nav')->fresh()->type);
        $this->assertSame('widget', $this->fullWidth('footer')->fresh()->type);
        $this->assertSame(0, GlobalSection::where('site_id', $this->site->id)->count());
        $this->assertArrayNotHasKey('grid_areas', $this->site->fresh()->settings);
    }

    public function test_menu_area_output_is_unchanged_by_conversion(): void
    {
        $navSegment = function () {
            $html = app(\App\Domain\Publishing\Services\BuildPageService::class)->build($this->home->fresh(), $this->site->fresh()->theme, $this->site->fresh());
            $start = strpos($html, 'class="pos-nav"');
            $end = strpos($html, 'class="pos-main"');
            $this->assertNotFalse($start);
            $this->assertNotFalse($end);

            return substr($html, $start, $end - $start);
        };

        $before = $navSegment();
        app(GridAreaConverter::class)->convert($this->site, dryRun: false);

        $this->assertStringContainsString('About us', $before);
        $this->assertSame($before, $navSegment());
    }

    public function test_only_selected_areas_are_converted(): void
    {
        app(GridAreaConverter::class)->convert($this->site, dryRun: false, onlyAreas: ['footer']);

        $this->assertSame('section', $this->fullWidth('footer')->fresh()->type);
        $this->assertSame('menu', $this->fullWidth('nav')->fresh()->type);
    }
}
