<?php

namespace Tests\Feature\GlobalSections;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\GlobalSections\Services\GlobalSectionService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\BlockTemplate;
use App\Models\GlobalSection;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Builder Experience P2 — Global Sections core: referenced (not copied) into a
 * page, inlined as flat HTML at publish, and editing the section cascades
 * staleness to embedding pages via the existing references engine.
 */
class GlobalSectionCoreTest extends TestCase
{
    private Site $site;
    private GlobalSectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->service = app(GlobalSectionService::class);
    }

    private function sectionWith(string $marker): GlobalSection
    {
        $section = $this->service->create($this->site, 'Shared CTA');
        $this->service->syncBlocks($section, [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [], 'children' => [
                ['type' => 'text', 'level' => 'module', 'order' => 0, 'data' => ['content' => $marker]],
            ],
        ]]);
        $this->service->publish($section);
        return $section->fresh();
    }

    private function pageEmbedding(GlobalSection $section): Page
    {
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'global_ref', 'order' => 0, 'data' => ['sectionId' => $section->id]],
        ]);
        return $page;
    }

    public function test_reference_edge_is_recorded_when_a_page_embeds_a_global(): void
    {
        $section = $this->sectionWith('MARKER');
        $page = $this->pageEmbedding($section);

        $this->assertDatabaseHas('entity_references', [
            'source_type' => 'page', 'source_id' => $page->id,
            'target_type' => 'global_section', 'target_id' => $section->id,
            'kind' => 'embeds',
        ]);
    }

    public function test_published_tree_is_inlined_into_the_page_html(): void
    {
        $section = $this->sectionWith('GLOBALMARKER123');
        $page = $this->pageEmbedding($section);

        $html = app(BuildPageService::class)->build($page->fresh(), $this->site->theme, $this->site);

        $this->assertStringContainsString('GLOBALMARKER123', $html);
        // no runtime lookup markers — the tree is inlined as flat HTML
        $this->assertStringNotContainsString('global_ref: no section', $html);
    }

    public function test_unpublished_section_renders_nothing(): void
    {
        $section = $this->sectionWith('SHOULDHIDE');
        $section->update(['status' => 'draft']);
        $page = $this->pageEmbedding($section);

        $html = app(BuildPageService::class)->build($page->fresh(), $this->site->theme, $this->site);
        $this->assertStringNotContainsString('SHOULDHIDE', $html);
    }

    public function test_editing_and_publishing_a_global_flags_embedding_pages_stale(): void
    {
        $section = $this->sectionWith('V1');
        $page = $this->pageEmbedding($section);
        // clear the stale flag set during initial embed/publish churn
        $page->update(['needs_republish' => false]);

        $stale = $this->service->publish($section);

        $this->assertGreaterThanOrEqual(1, $stale['pages']);
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_promote_from_library_creates_a_fresh_section_with_copied_blocks(): void
    {
        $item = BlockTemplate::create([
            'site_id' => $this->site->id,
            'name' => 'Promo band',
            'category' => 'custom',
            'kind' => 'section',
            'blocks_data' => [[
                'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
                'children' => [['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'type' => 'text', 'level' => 'module', 'order' => 0, 'data' => ['content' => 'PROMOTED']]],
            ]],
        ]);

        $section = $this->service->promoteFromLibrary($this->site, $item);

        $this->assertSame('Promo band', $section->name);
        $this->assertSame('draft', $section->status);
        // blocks copied with FRESH ids (not the library item's ids)
        $roots = $section->blocks()->whereNull('parent_block_id')->get();
        $this->assertCount(1, $roots);
        $this->assertNotSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $roots[0]->id);
        $this->assertDatabaseHas('blocks', ['blockable_type' => 'global_section', 'blockable_id' => $section->id, 'type' => 'text']);
    }
}
