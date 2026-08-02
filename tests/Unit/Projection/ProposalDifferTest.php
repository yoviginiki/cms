<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Domain\Projection\Proposals\ChangeOp;
use App\Domain\Projection\Proposals\ProposalDiffer;
use Tests\TestCase;

class ProposalDifferTest extends TestCase
{
    private function tree(): BlockTree
    {
        return new BlockTree([
            new BlockNode('h1', 'heading', ['text' => 'Old Title', 'level' => 'h2', '__style' => ['x' => 1]]),
            new BlockNode('r1', 'rich-text', ['content' => '<p>Body</p>']),
            new BlockNode('sec', 'section', [], [
                new BlockNode('list1', 'list', ['items' => [['title' => 'A'], ['title' => 'B']]]),
            ]),
        ]);
    }

    public function test_flatten_uses_canonical_addresses_and_skips_reserved(): void
    {
        $flat = (new ProposalDiffer())->flatten($this->tree());

        $this->assertSame('Old Title', $flat['h1#text']);
        $this->assertSame('h2', $flat['h1#level']);
        $this->assertSame('<p>Body</p>', $flat['r1#content']);
        $this->assertSame('A', $flat['list1#items.0.title']);
        $this->assertSame('B', $flat['list1#items.1.title']);
        $this->assertArrayNotHasKey('h1#__style', $flat);
        $this->assertArrayNotHasKey('h1#__style.x', $flat);
    }

    public function test_diff_detects_set_unset_and_ignores_noop(): void
    {
        $differ = new ProposalDiffer();
        $current = $differ->flatten($this->tree());

        $ops = $differ->diff($current, [
            'h1#text' => 'New Title',       // set (changed)
            'r1#content' => '<p>Body</p>',  // no-op (same)
            'h1#level' => null,             // unset
        ]);

        $this->assertCount(2, $ops);
        // sorted by address: h1#level before h1#text
        $this->assertSame('h1#level', $ops[0]->address);
        $this->assertSame(ChangeOp::UNSET, $ops[0]->op);
        $this->assertSame('h1#text', $ops[1]->address);
        $this->assertSame(ChangeOp::SET, $ops[1]->op);
        $this->assertSame('Old Title', $ops[1]->before);
        $this->assertSame('New Title', $ops[1]->after);
    }

    public function test_validate_flags_unresolvable_addresses(): void
    {
        $differ = new ProposalDiffer();
        $current = $differ->flatten($this->tree());

        $ops = [
            new ChangeOp('h1#text', ChangeOp::SET, 'Old Title', 'X'),        // valid block+field
            new ChangeOp('ghost#text', ChangeOp::SET, null, 'Y'),            // unknown block
            new ChangeOp('h1#missing', ChangeOp::UNSET, null, null),         // known block, missing field
        ];

        $invalid = $differ->validate($ops, $current);

        $this->assertContains('ghost#text', $invalid);
        $this->assertContains('h1#missing', $invalid);
        $this->assertNotContains('h1#text', $invalid);
    }

    public function test_diff_is_deterministic(): void
    {
        $differ = new ProposalDiffer();
        $current = $differ->flatten($this->tree());
        $proposed = ['r1#content' => '<p>New</p>', 'h1#text' => 'T'];

        $a = array_map(fn ($o) => $o->toArray(), $differ->diff($current, $proposed));
        $b = array_map(fn ($o) => $o->toArray(), $differ->diff($current, $proposed));
        $this->assertSame($a, $b);
    }
}
