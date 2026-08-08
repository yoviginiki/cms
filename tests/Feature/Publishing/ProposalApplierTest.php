<?php

namespace Tests\Feature\Publishing;

use App\Domain\Projection\Proposals\ChangeOp;
use App\Domain\Projection\Proposals\ProposalApplier;
use App\Models\Block;
use App\Models\Page;
use Tests\TestCase;

class ProposalApplierTest extends TestCase
{
    private function pageWithHeading(): array
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $block = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'Old Title', 'level' => 'h2'],
        ]);

        return [$page, $block];
    }

    public function test_applies_accepted_set_op_to_live_block(): void
    {
        [$page, $block] = $this->pageWithHeading();

        $result = app(ProposalApplier::class)->apply($page, [
            new ChangeOp("{$block->id}#text", ChangeOp::SET, 'Old Title', 'New Title'),
        ]);

        $this->assertSame(1, $result['applied']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame('New Title', $block->fresh()->data['text']);
    }

    public function test_stale_op_is_skipped_not_applied(): void
    {
        [$page, $block] = $this->pageWithHeading();

        $result = app(ProposalApplier::class)->apply($page, [
            new ChangeOp("{$block->id}#text", ChangeOp::SET, 'WRONG BASE', 'New Title'),
        ]);

        $this->assertSame(0, $result['applied']);
        $this->assertStringContainsString('stale', $result['skipped'][0]['reason']);
        $this->assertSame('Old Title', $block->fresh()->data['text']); // untouched
    }

    public function test_unknown_block_is_skipped(): void
    {
        [$page] = $this->pageWithHeading();

        $result = app(ProposalApplier::class)->apply($page, [
            new ChangeOp('00000000-0000-0000-0000-000000000000#text', ChangeOp::SET, null, 'X'),
        ]);

        $this->assertSame(0, $result['applied']);
        $this->assertStringContainsString('not found', $result['skipped'][0]['reason']);
    }

    public function test_unset_removes_the_field(): void
    {
        [$page, $block] = $this->pageWithHeading();

        $result = app(ProposalApplier::class)->apply($page, [
            new ChangeOp("{$block->id}#text", ChangeOp::UNSET, 'Old Title', null),
        ]);

        $this->assertSame(1, $result['applied']);
        $this->assertArrayNotHasKey('text', $block->fresh()->data);
    }

    public function test_reserved_path_is_rejected(): void
    {
        [$page, $block] = $this->pageWithHeading();

        $result = app(ProposalApplier::class)->apply($page, [
            new ChangeOp("{$block->id}#__style.color", ChangeOp::UNSET, null, null),
        ]);

        $this->assertSame(0, $result['applied']);
        $this->assertNotEmpty($result['skipped']);
    }
}
