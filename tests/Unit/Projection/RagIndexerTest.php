<?php

namespace Tests\Unit\Projection;

use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\RichTextBlockDefinition;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Domain\Projection\Projection;
use App\Domain\Projection\ProjectionBuilder;
use App\Domain\Projection\ProjectionContext;
use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\RagIndexer;
use Tests\TestCase;

class RagIndexerTest extends TestCase
{
    private function projection(string $bodyA = 'First body.', string $bodyB = 'Second body.'): Projection
    {
        $map = [
            'heading' => (new HeadingBlockDefinition())->projection(),
            'rich-text' => (new RichTextBlockDefinition())->projection(),
        ];
        $resolver = fn (string $t): ?BlockProjection => $map[$t] ?? null;
        $ctx = new ProjectionContext('page-9', 'ver-9', '/d/', 'Guide', 'en', null, null, $resolver);

        return (new ProjectionBuilder())->build(new BlockTree([
            new BlockNode('h1', 'heading', ['level' => 'h2', 'text' => 'Chapter']),
            new BlockNode('a', 'rich-text', ['content' => "<p>{$bodyA}</p>"]),
            new BlockNode('b', 'rich-text', ['content' => "<p>{$bodyB}</p>"]),
        ]), $ctx);
    }

    public function test_indexes_segments_with_provenance_and_embeddings(): void
    {
        $indexer = new RagIndexer(new HashEmbedder(16));
        $chunks = $indexer->index($this->projection());

        $this->assertCount(2, $chunks); // two rich-text segments
        $chunk = $chunks[0];
        $this->assertSame('page-9', $chunk->pageId);
        $this->assertSame('ver-9', $chunk->pageVersionId);
        $this->assertSame(['Guide', 'Chapter'], $chunk->headingPath);
        $this->assertCount(16, $chunk->embedding);
        $this->assertSame('hash-16', $chunk->model);
        $this->assertSame(hash('sha256', $chunk->text), $chunk->hash);
    }

    public function test_indexing_is_deterministic(): void
    {
        $indexer = new RagIndexer(new HashEmbedder(16));
        $a = array_map(fn ($c) => $c->toArray(), $indexer->index($this->projection()));
        $b = array_map(fn ($c) => $c->toArray(), $indexer->index($this->projection()));
        $this->assertSame($a, $b);
    }

    public function test_incremental_reindex_only_touches_changed_chunks(): void
    {
        $indexer = new RagIndexer(new HashEmbedder(16));

        $v1 = $indexer->index($this->projection('First body.', 'Second body.'));
        $known = [];
        foreach ($v1 as $chunk) {
            $known[$chunk->hash] = true;
        }

        // Change only block B's body.
        $changed = $indexer->indexChanged(
            $this->projection('First body.', 'DIFFERENT second body.'),
            $known,
        );

        $this->assertCount(1, $changed);
        $this->assertStringContainsString('DIFFERENT', $changed[0]->text);
    }
}
