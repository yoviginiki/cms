<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * F13 (audit 2026-09-22) — explicit content revision on the owning record,
 * compared-and-incremented inside the write transaction; required for the
 * interactive API; also bumped by inline patches so a stale full save
 * cannot overwrite them.
 */
class ContentRevisionTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->create(['site_id' => $this->site->id]);
    }

    private function url(): string
    {
        return "/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/blocks";
    }

    private function tree(string $text): array
    {
        return ['blocks' => [['type' => 'text', 'data' => ['content' => $text], 'order' => 0]]];
    }

    public function test_revision_flow_and_required_expected_version(): void
    {
        $v0 = $this->actingAsOwner()->getJson($this->url(), $this->apiHeaders())->assertOk()->json('version');
        $this->assertSame('0', $v0);

        // missing expected_version → refused (interactive API contract)
        $this->actingAsOwner()->putJson($this->url(), $this->tree('a'), $this->apiHeaders())->assertStatus(422);
        $this->assertSame(0, Block::where('blockable_id', $this->page->id)->count());

        // explicit overwrite is the programmatic escape hatch
        $v1 = $this->actingAsOwner()->putJson($this->url(), $this->tree('a') + ['overwrite' => true], $this->apiHeaders())
            ->assertOk()->json('version');
        $this->assertSame('1', $v1);

        // two saves in the same second get different revisions
        $v2 = $this->actingAsOwner()->putJson($this->url(), $this->tree('b') + ['expected_version' => $v1], $this->apiHeaders())
            ->assertOk()->json('version');
        $this->assertSame('2', $v2);
        $this->assertNotSame($v1, $v2);

        // stale → 409 with the current version, nothing written
        $this->actingAsOwner()->putJson($this->url(), $this->tree('c') + ['expected_version' => $v1], $this->apiHeaders())
            ->assertStatus(409)->assertJsonPath('current_version', '2');
        $this->assertSame('b', Block::where('blockable_id', $this->page->id)->first()->data['content']);
        $this->assertSame('2', $this->page->fresh()->content_revision . '');
    }

    public function test_inline_patch_bumps_the_revision_so_a_stale_full_save_is_refused(): void
    {
        app(BlockService::class)->syncBlocks($this->page, [['type' => 'heading', 'order' => 0, 'data' => ['text' => 'Hi', 'level' => 'h1']]]);
        $v = $this->actingAsOwner()->getJson($this->url(), $this->apiHeaders())->json('version');
        $block = Block::where('blockable_id', $this->page->id)->firstOrFail();

        $inline = $this->actingAsOwner()->patchJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline/blocks", [
            'expected_version' => $v,
            'patches' => [['block' => $block->id, 'field' => 'text', 'value' => 'Inline edit']],
        ], $this->apiHeaders())->assertOk();
        $this->assertNotSame($v, $inline->json('version'));

        // A full save from BEFORE the inline edit must not clobber it.
        $this->actingAsOwner()->putJson($this->url(), [
            'expected_version' => $v,
            'blocks' => [['id' => $block->id, 'type' => 'heading', 'order' => 0, 'data' => ['text' => 'Stale', 'level' => 'h1']]],
        ], $this->apiHeaders())->assertStatus(409);
        $this->assertSame('Inline edit', $block->fresh()->data['text']);

        // Inline patch with a stale version is refused too.
        $this->actingAsOwner()->patchJson("/api/v1/sites/{$this->site->id}/pages/{$this->page->id}/inline/blocks", [
            'expected_version' => $v,
            'patches' => [['block' => $block->id, 'field' => 'text', 'value' => 'Older']],
        ], $this->apiHeaders())->assertStatus(409);
    }

    public function test_version_restore_and_programmatic_writers_still_advance_the_revision(): void
    {
        $svc = app(BlockService::class);
        $svc->syncBlocks($this->page, [['type' => 'text', 'order' => 0, 'data' => ['content' => 'x']]]);
        $this->assertSame('1', $svc->blocksVersion($this->page));
        $svc->syncBlocks($this->page, [['type' => 'text', 'order' => 0, 'data' => ['content' => 'y']]]);
        $this->assertSame('2', $svc->blocksVersion($this->page));
    }
}
