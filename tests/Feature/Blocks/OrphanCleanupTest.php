<?php

namespace Tests\Feature\Blocks;

use App\Models\Block;
use App\Models\Page;
use Tests\TestCase;

/**
 * FIX-A3a — force-deleting a blockable purges its (FK-less, polymorphic) blocks.
 * Soft-delete keeps them (for restore).
 */
class OrphanCleanupTest extends TestCase
{
    public function test_force_delete_purges_blocks_soft_delete_keeps_them(): void
    {
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();

        $this->assertGreaterThan(0, $page->blocks()->count());

        // Soft delete: blocks remain (page can be restored)
        $page->delete();
        $this->assertGreaterThan(0, Block::where('blockable_type', $page->getMorphClass())
            ->where('blockable_id', $page->id)->count());

        // Force delete: blocks purged
        $page->forceDelete();
        $this->assertSame(0, Block::where('blockable_type', $page->getMorphClass())
            ->where('blockable_id', $page->id)->count());
    }
}
