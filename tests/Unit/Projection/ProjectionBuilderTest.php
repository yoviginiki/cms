<?php

namespace Tests\Unit\Projection;

use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Blocks\Definitions\PostContentBlockDefinition;
use App\Domain\Blocks\Definitions\RichTextBlockDefinition;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Domain\Projection\Projection;
use App\Domain\Projection\ProjectionBuilder;
use App\Domain\Projection\ProjectionContext;
use App\Domain\Projection\ProjectionView;
use Tests\TestCase;

class ProjectionBuilderTest extends TestCase
{
    private ProjectionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ProjectionBuilder();
    }

    /** Resolver wired to the REAL pilot descriptors, proving the actual contract. */
    private function resolver(): \Closure
    {
        $map = [
            'heading' => (new HeadingBlockDefinition())->projection(),
            'rich-text' => (new RichTextBlockDefinition())->projection(),
            'image' => (new ImageBlockDefinition())->projection(),
            'post-content' => (new PostContentBlockDefinition())->projection(),
        ];

        return fn (string $type): ?BlockProjection => $map[$type] ?? null;
    }

    private function ctx(?\Closure $resolver = null): ProjectionContext
    {
        return new ProjectionContext(
            pageId: 'page-1',
            pageVersionId: 'ver-1',
            url: '/demo/',
            title: 'Page Title',
            language: 'en',
            publishedAt: '2026-01-01T00:00:00Z',
            modifiedAt: '2026-01-02T00:00:00Z',
            descriptorResolver: $resolver ?? $this->resolver(),
        );
    }

    private function build(BlockTree $tree, ?ProjectionContext $ctx = null): Projection
    {
        return $this->builder->build($tree, $ctx ?? $this->ctx());
    }

    private function heading(string $id, string $level, string $text): BlockNode
    {
        return new BlockNode($id, 'heading', ['level' => $level, 'text' => $text]);
    }

    private function richText(string $id, string $html): BlockNode
    {
        return new BlockNode($id, 'rich-text', ['content' => $html]);
    }

    private function image(string $id, array $data): BlockNode
    {
        return new BlockNode($id, 'image', $data);
    }

    // 1 -------------------------------------------------------------------
    public function test_determinism_100_builds_are_byte_identical(): void
    {
        $tree = new BlockTree([
            $this->heading('h1', 'h2', 'Section'),
            $this->richText('r1', '<p>Some <a href="/x">body</a> text here.</p>'),
            $this->image('i1', ['asset_id' => 'a-1', 'alt' => 'Alt']),
        ]);

        $first = $this->build($tree)->toJson();
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame($first, $this->build($tree)->toJson(), "build #{$i} diverged");
        }
    }

    // 2 -------------------------------------------------------------------
    public function test_input_key_order_does_not_change_output(): void
    {
        $a = new BlockTree([
            new BlockNode('i1', 'image', ['asset_id' => 'a-1', 'alt' => 'Alt', 'url' => '']),
        ]);
        $b = new BlockTree([
            new BlockNode('i1', 'image', ['url' => '', 'alt' => 'Alt', 'asset_id' => 'a-1']),
        ]);

        $this->assertSame($this->build($a)->toJson(), $this->build($b)->toJson());
    }

    // 3 -------------------------------------------------------------------
    public function test_unmarked_block_appears_nowhere(): void
    {
        $tree = new BlockTree([
            new BlockNode('u1', 'unknown-block', ['title' => 'Looks like a heading but is not']),
        ]);
        $p = $this->build($tree)->toArray();

        $this->assertSame([], $p['structure']);
        $this->assertSame([], $p['schema_org']['@graph']);
        $this->assertSame([], $p['segments']);
        $this->assertSame([], $p['inventory']['heading_outline']);
        $this->assertStringNotContainsString('Looks like a heading', $this->build($tree)->toJson());
    }

    // 4 -------------------------------------------------------------------
    public function test_unmarked_parent_with_marked_children_processes_children(): void
    {
        $tree = new BlockTree([
            new BlockNode('sec', 'section', [], [
                new BlockNode('col', 'unknown-column', [], [
                    $this->image('i1', ['asset_id' => 'a-1', 'alt' => 'Deep']),
                ]),
            ]),
        ]);
        $p = $this->build($tree)->toArray();

        // The image bubbles to top-level structure but keeps its true coordinates.
        $this->assertCount(1, $p['structure']);
        $this->assertSame('i1', $p['structure'][0]['address']);
        $this->assertSame('0.0.0', $p['structure'][0]['path']);
        $this->assertSame(3, $p['structure'][0]['depth']);
        $this->assertCount(1, $p['inventory']['assets']);
    }

    // 5 -------------------------------------------------------------------
    public function test_empty_page_yields_valid_empty_projection(): void
    {
        $p = $this->build(new BlockTree([
            new BlockNode('u1', 'unknown-a', []),
            new BlockNode('u2', 'unknown-b', []),
        ]))->toArray();

        $this->assertSame('1.0', $p['projection_version']);
        $this->assertSame([], $p['structure']);
        $this->assertSame([], $p['segments']);
        $this->assertSame(0, $p['inventory']['word_count']);
        $this->assertSame('https://schema.org', $p['schema_org']['@context']);
        $this->assertSame([], $p['schema_org']['@graph']);
    }

    // 6 -------------------------------------------------------------------
    public function test_deep_nesting_path_and_depth_are_correct(): void
    {
        // 6 nested headings, each the sole child of the previous.
        $leaf = $this->heading('h6node', 'h6', 'Deepest');
        $node = $leaf;
        for ($level = 5; $level >= 1; $level--) {
            $node = new BlockNode("h{$level}node", 'heading', ['level' => "h{$level}", 'text' => "L{$level}"], [$node]);
        }
        $p = $this->build(new BlockTree([$node]))->toArray();

        // Walk down the nested structure collecting path/depth.
        $entry = $p['structure'][0];
        $seenDepths = [];
        $path = $entry['path'];
        while (true) {
            $seenDepths[] = $entry['depth'];
            $this->assertSame(substr_count($entry['path'], '.') + 1, $entry['depth']);
            if (empty($entry['children'])) {
                $path = $entry['path'];
                break;
            }
            $entry = $entry['children'][0];
        }
        $this->assertSame([1, 2, 3, 4, 5, 6], $seenDepths);
        $this->assertSame('0.0.0.0.0.0', $path);
    }

    // 7 -------------------------------------------------------------------
    public function test_segment_hash_of_block_b_is_stable_when_block_a_changes(): void
    {
        $treeV1 = new BlockTree([
            $this->richText('A', '<p>Original A content.</p>'),
            $this->richText('B', '<p>Stable B content.</p>'),
        ]);
        $treeV2 = new BlockTree([
            $this->richText('A', '<p>COMPLETELY different A content now.</p>'),
            $this->richText('B', '<p>Stable B content.</p>'),
        ]);

        $hashB = fn (array $p) => collect($p['segments'])->firstWhere('address', 'B#content')['hash'];

        $v1 = $this->build($treeV1)->toArray();
        $v2 = $this->build($treeV2)->toArray();

        $this->assertNotSame(
            collect($v1['segments'])->firstWhere('address', 'A#content')['hash'],
            collect($v2['segments'])->firstWhere('address', 'A#content')['hash'],
            'A hash should change'
        );
        $this->assertSame($hashB($v1), $hashB($v2), 'B hash must be stable');
    }

    // 8 -------------------------------------------------------------------
    public function test_pinned_snapshot(): void
    {
        $tree = new BlockTree([
            $this->heading('h1', 'h2', 'Intro'),
            $this->richText('r1', '<p>Body with <a href="/a">one</a> and <a href="https://ext.example/b">two</a> links.</p>'),
            new BlockNode('sec', 'section', [], [
                $this->image('i1', ['asset_id' => 'asset-9', 'alt' => 'A cat', 'url' => '']),
            ]),
            new BlockNode('pc', 'post-content', []),
        ]);

        $json = $this->build($tree)->toJson();

        // Pin: any drift in the deterministic output breaks the build.
        $this->assertSame(self::PINNED_SNAPSHOT_HASH, hash('sha256', $json), "projection output drifted:\n{$json}");
    }

    private const PINNED_SNAPSHOT_HASH = 'd013e6dc761bdb3a7b4d7a792c8077b5fcc3dcb180fbd118d797d4274ef5e78b';

    // 9 -------------------------------------------------------------------
    public function test_inventory_completeness(): void
    {
        $tree = new BlockTree([
            $this->heading('hh1', 'h2', 'H One'),
            $this->heading('hh2', 'h3', 'H Two'),
            $this->heading('hh3', 'h2', 'H Three'),
            $this->heading('hh4', 'h3', 'H Four'),
            $this->richText('rr1', '<p><a href="/1">1</a> <a href="/2">2</a> <a href="/3">3</a></p>'),
            $this->richText('rr2', '<p><a href="https://x/4">4</a></p>'),
            $this->image('im1', ['asset_id' => 'a1', 'alt' => 'one', 'url' => 'https://cdn/5.jpg']),
            $this->image('im2', ['asset_id' => 'a2', 'alt' => 'two']),
            $this->image('im3', ['asset_id' => 'a3', 'alt' => 'three']),
        ]);
        $inv = $this->build($tree)->toArray()['inventory'];

        $this->assertCount(5, $inv['outbound_links'], 'expected 5 links');
        $this->assertCount(3, $inv['assets'], 'expected 3 assets');
        $this->assertCount(4, $inv['heading_outline'], 'expected 4 headings');
        $this->assertGreaterThan(0, $inv['word_count']);
    }

    // 10 ------------------------------------------------------------------
    public function test_views_are_subsets_of_the_full_projection(): void
    {
        $tree = new BlockTree([
            $this->heading('h1', 'h2', 'Section'),
            $this->richText('r1', '<p>Body <a href="/x">link</a>.</p>'),
            $this->image('i1', ['asset_id' => 'a-1', 'alt' => 'Alt']),
        ]);
        $p = $this->build($tree);
        $full = $p->toArray();

        $this->assertSame($full, $p->view(ProjectionView::Internal));

        $public = $p->view(ProjectionView::Public);
        $this->assertSame($full['schema_org'], $public['schema_org']);
        $this->assertSame($full['page'], $public['page']);
        $this->assertEmpty(array_diff(array_keys($public), array_keys($full)));
        // Every public structure entry corresponds to a real full block.
        $fullByAddr = collect($full['structure'])->keyBy('address');
        foreach ($public['structure'] as $entry) {
            $this->assertTrue($fullByAddr->has($entry['address']));
            $this->assertSame($fullByAddr[$entry['address']]['path'], $entry['path']);
            $this->assertArrayNotHasKey('fields', $entry); // minimal view drops fields
        }

        $rag = $p->view(ProjectionView::Rag);
        $this->assertSame($full['segments'], $rag['segments']);
        $this->assertSame($full['source'], $rag['source']);

        $inventory = $p->view(ProjectionView::Inventory);
        $this->assertSame($full['inventory'], $inventory['inventory']);
    }

    // acceptance: build of a realistic page is under 50ms ------------------
    public function test_realistic_page_builds_under_50ms(): void
    {
        $blocks = [];
        for ($i = 0; $i < 40; $i++) {
            $blocks[] = $this->heading("h{$i}", 'h2', "Heading {$i}");
            $blocks[] = $this->richText("r{$i}", "<p>Paragraph {$i} with a <a href=\"/l{$i}\">link</a> and more words to count.</p>");
            $blocks[] = $this->image("i{$i}", ['asset_id' => "a{$i}", 'alt' => "Image {$i}"]);
        }
        $tree = new BlockTree($blocks);

        $start = hrtime(true);
        $this->build($tree);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan(50.0, $elapsedMs, "build took {$elapsedMs}ms");
    }
}
