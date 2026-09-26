<?php

namespace Tests\Feature\Api;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\GlobalSection;
use App\Models\Grid;
use App\Models\GridPosition;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Grid areas as blocks — stage 3: a new site is created with a working
 * header and footer (block sections) and its starter pages in menus, so the
 * first publish already has navigation and a footer (audit B1/B2).
 */
class NewSiteChromeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
    }

    private function createSite(string $name = 'Acme Studio'): Site
    {
        $id = $this->actingAsOwner()->postJson('/api/v1/sites', ['name' => $name], $this->apiHeaders())
            ->assertCreated()->json('data.id');

        return Site::findOrFail($id);
    }

    public function test_new_site_gets_published_header_and_footer_sections_in_every_preset_grid(): void
    {
        $site = $this->createSite();

        $this->assertSame('blocks', $site->settings['grid_areas']);
        $this->assertSame('unified', $site->settings['post_grid']);

        $sections = GlobalSection::where('site_id', $site->id)->pluck('status', 'name');
        $this->assertSame(['Site footer' => 'published', 'Site header' => 'published'], $sections->sortKeys()->all());
        $headerId = GlobalSection::where('site_id', $site->id)->where('name', 'Site header')->value('id');
        $footerId = GlobalSection::where('site_id', $site->id)->where('name', 'Site footer')->value('id');

        foreach (Grid::where('site_id', $site->id)->get() as $grid) {
            $this->assertStringNotContainsString('nav', $grid->areas, $grid->name);
            $this->assertSame(count(preg_split('/\s+/', trim($grid->row_tracks))), preg_match_all('/"[^"]*"/', $grid->areas), "{$grid->name}: one track per row");
            foreach (($grid->breakpoints_json ?? []) as $bp) {
                $this->assertStringNotContainsString('nav', $bp['areas'] ?? '', $grid->name);
            }
            $byArea = GridPosition::where('grid_id', $grid->id)->get()->keyBy('area_name');
            $this->assertFalse($byArea->has('nav'));
            $this->assertSame($headerId, $byArea['header']->config_json['section_id'] ?? null, $grid->name);
            $this->assertSame($footerId, $byArea['footer']->config_json['section_id'] ?? null, $grid->name);
        }
    }

    public function test_starter_template_menus_and_first_build_has_navigation_and_footer(): void
    {
        $site = $this->createSite();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/apply-template", ['template' => 'business'], $this->apiHeaders())
            ->assertOk();

        $header = Menu::where('site_id', $site->id)->where('location', 'header')->firstOrFail();
        $footer = Menu::where('site_id', $site->id)->where('location', 'footer')->firstOrFail();
        $this->assertSame(['Home', 'About', 'Services', 'Team', 'Contact'], $header->items()->orderBy('sort_order')->pluck('label')->all());
        $this->assertSame(2, $footer->items()->count());

        $home = Page::where('site_id', $site->id)->where('slug', 'home')->firstOrFail();
        $html = app(BuildPageService::class)->build($home, $site->fresh()->theme, $site->fresh());

        $this->assertStringContainsString('Acme Studio', $html);                // site identity
        $this->assertMatchesRegularExpression('#href="[^"]*/services/?"#', $html); // header menu
        $this->assertStringContainsString('© ' . date('Y') . ' Acme Studio', $html); // copyright
        $this->assertStringContainsString('window.scrollTo', $html);           // back to top
    }

    public function test_reapplying_a_template_does_not_duplicate_or_replace_menus(): void
    {
        $site = $this->createSite();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/apply-template", ['template' => 'blog'], $this->apiHeaders())->assertOk();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/apply-template", ['template' => 'business'], $this->apiHeaders())->assertOk();

        $this->assertSame(1, Menu::where('site_id', $site->id)->where('location', 'header')->count());
        $this->assertSame(1, Menu::where('site_id', $site->id)->where('location', 'footer')->count());
    }

    public function test_cloning_a_legacy_site_keeps_its_legacy_rendering(): void
    {
        $legacy = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => true]]);

        $cloneId = $this->actingAsOwner()->postJson("/api/v1/sites/{$legacy->id}/clone", ['name' => 'Legacy copy'], $this->apiHeaders())
            ->assertSuccessful()->json('data.id');
        $clone = Site::findOrFail($cloneId);

        $this->assertArrayNotHasKey('grid_areas', $clone->settings ?? []);
        $this->assertArrayNotHasKey('post_grid', $clone->settings ?? []);
        $this->assertSame(0, GlobalSection::where('site_id', $clone->id)->count());
    }
}
