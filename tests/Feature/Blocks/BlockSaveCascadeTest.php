<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\ThemeOverride;
use Tests\TestCase;

/**
 * FIX-C11a — the destructive bulk-replace in syncBlocks must not cascade away
 * block-scoped theme_overrides for blocks that survive the round trip.
 */
class BlockSaveCascadeTest extends TestCase
{
    public function test_resaving_blocks_preserves_block_scoped_theme_override(): void
    {
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $service = app(BlockService::class);

        $block = Block::where('blockable_type', $page->getMorphClass())
            ->where('blockable_id', $page->id)->firstOrFail();

        $override = ThemeOverride::create([
            'tenant_id' => $this->tenant->id,
            'site_id' => $site->id,
            'block_id' => $block->id,
            'scope' => 'block',
            'mode' => 'light',
            'token_path' => 'color.primary',
            'value' => ['hex' => '#ff0000'],
        ]);

        // Re-save the exact same block tree (as the editor does on any save)
        $tree = $service->getBlockTree($page);
        $service->syncBlocks($page, $tree);

        $this->assertSame(
            1,
            ThemeOverride::where('block_id', $block->id)->where('token_path', 'color.primary')->count(),
            'block-scoped theme override was lost on re-save',
        );
    }
}
