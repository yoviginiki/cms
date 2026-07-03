<?php

namespace Tests\Feature\References;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Asset;
use App\Models\EntityReference;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Saving a block tree recomputes the source's edges atomically:
 * new references appear, removed references disappear.
 */
class RecomputeOnSaveTest extends TestCase
{
    private Site $site;
    private Page $page;
    private BlockService $blocks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        $this->blocks = app(BlockService::class);
    }

    private function pageEdges(): array
    {
        return EntityReference::forSource('page', $this->page->id)
            ->get(['target_type', 'target_id', 'kind'])
            ->map(fn ($e) => [$e->target_type, $e->target_id, $e->kind])
            ->all();
    }

    public function test_sync_creates_edges_for_referencing_blocks(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->blocks->syncBlocks($this->page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $asset->id]],
            ['type' => 'heading', 'order' => 1, 'data' => ['text' => 'Hi']],
        ]);

        $this->assertSame([['asset', $asset->id, 'uses_asset']], $this->pageEdges());
    }

    public function test_resync_removes_edges_for_deleted_blocks_and_adds_new_ones(): void
    {
        $old = Asset::factory()->create(['site_id' => $this->site->id]);
        $new = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->blocks->syncBlocks($this->page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $old->id]],
        ]);
        $this->assertSame([['asset', $old->id, 'uses_asset']], $this->pageEdges());

        // Replace the image block with one pointing at a different asset
        $this->blocks->syncBlocks($this->page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $new->id]],
        ]);
        $this->assertSame([['asset', $new->id, 'uses_asset']], $this->pageEdges());

        // Remove all referencing blocks — edges go away entirely
        $this->blocks->syncBlocks($this->page, [
            ['type' => 'heading', 'order' => 0, 'data' => ['text' => 'plain']],
        ]);
        $this->assertSame([], $this->pageEdges());
    }

    public function test_duplicate_references_are_deduped_to_one_edge(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->blocks->syncBlocks($this->page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $asset->id]],
            ['type' => 'image', 'order' => 1, 'data' => ['asset_id' => $asset->id]],
        ]);

        $this->assertCount(1, $this->pageEdges());
    }

    public function test_nested_children_blocks_are_extracted_too(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->blocks->syncBlocks($this->page, [
            ['type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [], 'children' => [
                ['type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [], 'children' => [
                    ['type' => 'image', 'level' => 'module', 'order' => 0, 'data' => ['asset_id' => $asset->id]],
                ]],
            ]],
        ]);

        $this->assertSame([['asset', $asset->id, 'uses_asset']], $this->pageEdges());
    }
}
