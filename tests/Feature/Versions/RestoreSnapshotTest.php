<?php

namespace Tests\Feature\Versions;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use Tests\TestCase;

/**
 * P4: restoring a version snapshots the CURRENT state first, so a restore is
 * itself undoable (you can restore back to where you were).
 */
class RestoreSnapshotTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_restore_snapshots_current_state_before_restoring(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id]);

        // current (saved) state = V2
        app(BlockService::class)->syncBlocks($page, [['type' => 'text', 'order' => 0, 'data' => ['content' => 'V2']]]);

        // an older saved version = V1
        $v1 = PageVersion::create([
            'page_id' => $page->id,
            'blocks_snapshot' => [['type' => 'text', 'order' => 0, 'data' => ['content' => 'V1']]],
            'seo_snapshot' => [],
            'published_by' => $this->owner->id,
            'published_at' => now(),
            'version_number' => 1,
        ]);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}/versions/{$v1->id}/restore")
            ->assertOk();

        // the page now holds V1
        $tree = app(BlockService::class)->getBlockTree($page->fresh());
        $this->assertStringContainsString('V1', json_encode($tree));

        // and a snapshot-before version was created capturing V2 (so restore is undoable)
        $versions = PageVersion::where('page_id', $page->id)->orderByDesc('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertStringContainsString('V2', json_encode($versions->first()->blocks_snapshot));
    }
}
