<?php

namespace Tests\Feature\Api;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\GlobalSection;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\GridPosition;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Grid areas as blocks — stage 1 (docs/PLAN-GRID-AREAS-AS-BLOCKS.md): a grid
 * area of type "section" renders a Global Section's published blocks; one
 * section serves every grid that points at it; per-page override hides the
 * area or swaps the section; publishing the section flags the whole site.
 */
class SectionAreaTest extends TestCase
{
    private Site $site;
    private Grid $grid;
    private GridPosition $footer;
    private GlobalSection $siteFooter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->grid = Grid::create([
            'site_id' => $this->site->id, 'name' => 'Full Width', 'slug' => 'full-width-' . uniqid(),
            'col_tracks' => '1fr', 'row_tracks' => 'auto auto', 'areas' => '"main" "footer"', 'is_preset' => false,
        ]);
        GridPosition::create(['grid_id' => $this->grid->id, 'area_name' => 'main', 'label' => 'Main', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 1]);
        GridAssignment::create(['site_id' => $this->site->id, 'grid_id' => $this->grid->id, 'assignable_type' => 'default', 'assignable_id' => null, 'priority' => 9999]);

        $this->siteFooter = $this->section('Site footer', 'FOOTER FROM BLOCKS');
        $this->putPositions(['section_id' => $this->siteFooter->id])->assertOk();
        $this->footer = GridPosition::where('grid_id', $this->grid->id)->where('area_name', 'footer')->firstOrFail();
    }

    private function section(string $name, string $text, bool $publish = true): GlobalSection
    {
        $id = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/global-sections", ['name' => $name], $this->apiHeaders())
            ->assertCreated()->json('data.id');
        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}/global-sections/{$id}/blocks", ['overwrite' => true, 'blocks' => [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
            'children' => [['type' => 'row', 'level' => 'row', 'order' => 0, 'data' => ['layout' => '1'],
                'children' => [['type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [],
                    'children' => [['type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => ['text' => $text, 'level' => 'h2']]]]]]],
        ]]], $this->apiHeaders())->assertOk()->assertJsonStructure(['data', 'version']);
        if ($publish) {
            $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/global-sections/{$id}/publish", [], $this->apiHeaders())->assertOk();
        }

        return GlobalSection::findOrFail($id);
    }

    private function putPositions(array $footerConfig)
    {
        return $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}/grids/{$this->grid->id}/positions", ['positions' => [
            ['area_name' => 'main', 'label' => 'Main', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 1],
            ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'section', 'scope' => 'site', 'mobile_order' => 2, 'config_json' => $footerConfig],
        ]], $this->apiHeaders());
    }

    private function build(Page $page): string
    {
        return app(BuildPageService::class)->build($page->fresh(), $this->site->fresh()->theme, $this->site->fresh());
    }

    public function test_section_area_renders_the_published_section_blocks(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published']);

        $this->assertStringContainsString('FOOTER FROM BLOCKS', $this->build($page));

        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/global-sections/{$this->siteFooter->id}/unpublish", [], $this->apiHeaders())->assertOk();
        $this->assertStringNotContainsString('FOOTER FROM BLOCKS', $this->build($page));
    }

    public function test_area_must_point_at_a_section_of_this_site(): void
    {
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreign = GlobalSection::create(['site_id' => $other->id, 'name' => 'Foreign', 'status' => 'published']);

        $this->putPositions(['section_id' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors(['positions.1.config_json.section_id']);
    }

    public function test_publishing_an_area_section_flags_the_whole_site(): void
    {
        $this->site->update(['settings' => ['auto_publish' => false]]); // clears the stale flag from setup

        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/global-sections/{$this->siteFooter->id}/publish", [], $this->apiHeaders())
            ->assertOk()->assertJsonPath('meta.stale.site_wide', true);
        $this->assertArrayHasKey('stale', $this->site->fresh()->settings);

        $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/global-sections/{$this->siteFooter->id}", $this->apiHeaders())
            ->assertOk()->assertJsonPath('data.grid_areas.0.area', 'footer');
    }

    public function test_a_page_can_hide_the_area_or_use_another_section(): void
    {
        $landing = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published']);
        $normal = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published']);
        $minimal = $this->section('Minimal footer', 'MINIMAL FOOTER');

        $base = "/api/v1/sites/{$this->site->id}/grid-positions/{$this->footer->id}/override";
        $this->actingAsOwner()->postJson($base, ['page_id' => $landing->id, 'content_json' => ['hidden' => true]], $this->apiHeaders())->assertCreated();
        $this->assertStringNotContainsString('FOOTER FROM BLOCKS', $this->build($landing));
        $this->assertStringContainsString('FOOTER FROM BLOCKS', $this->build($normal));

        $this->actingAsOwner()->postJson($base, ['page_id' => $landing->id, 'content_json' => ['section_id' => $minimal->id]], $this->apiHeaders())->assertCreated();
        $this->assertStringContainsString('MINIMAL FOOTER', $this->build($landing));

        $areas = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/pages/{$landing->id}/resolved-grid", $this->apiHeaders())
            ->assertOk()->json('data.areas');
        $this->assertSame('Site footer', $areas[0]['section']['name']);
        $this->assertSame($minimal->id, $areas[0]['override']['section_id']);
    }

    public function test_section_blocks_endpoint_rejects_invalid_trees(): void
    {
        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}/global-sections/{$this->siteFooter->id}/blocks", ['overwrite' => true, 'blocks' => [
            ['type' => 'not-a-block', 'order' => 0, 'data' => []],
        ]], $this->apiHeaders())->assertStatus(422);
    }

    public function test_preview_marks_areas_for_the_overlay_but_published_html_does_not(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published']);
        $builder = app(BuildPageService::class);

        $preview = $builder->build($page->fresh(), $this->site->fresh()->theme, $this->site->fresh(), isPreview: true);
        $this->assertStringContainsString('data-sp-area="footer"', $preview);
        $this->assertStringContainsString('data-sp-section="' . $this->siteFooter->id . '"', $preview);
        $this->assertStringContainsString('data-sp-section-name="Site footer"', $preview);

        $this->assertStringNotContainsString('data-sp-area', $this->build($page));
    }

    public function test_admin_preview_injects_the_area_overlay(): void
    {
        $this->site->update(['slug' => 'area-site']);
        Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published', 'slug' => 'about']);

        $select = $this->actingAsOwner()->get("/sites/area-site/about?_grid={$this->grid->id}&_toolbar=0&_areas=1")->assertOk()->getContent();
        $this->assertStringContainsString('/grid-areas/overlay.js', $select);
        $this->assertStringContainsString('"mode":"select"', $select);

        $link = $this->actingAsOwner()->get('/sites/area-site/about')->assertOk()->getContent();
        $this->assertStringContainsString('"mode":"link"', $link);
        $this->assertStringContainsString('/admin/sites/area-site/sections/', $link);
    }
}
