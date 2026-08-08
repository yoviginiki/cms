<?php

namespace Tests\Unit\Projection;

use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Domain\Projection\ProjectionBuilder;
use App\Domain\Projection\ProjectionContext;
use App\Domain\Projection\ProjectionView;
use Tests\TestCase;

/**
 * Phase 6.1 — schema.org validation. A full online validator is unavailable in
 * this environment (no network); this asserts the generated JSON-LD is
 * STRUCTURALLY valid schema.org: correct @context, every node a known @type
 * with non-null values, and JSON round-trips losslessly.
 */
class ProjectionSchemaValidationTest extends TestCase
{
    /** schema.org types this layer is allowed to emit (see projection-vocabulary.md). */
    private const KNOWN_TYPES = ['ImageObject'];

    private function build(BlockTree $tree): array
    {
        $resolver = fn (string $t): ?BlockProjection => $t === 'image'
            ? (new ImageBlockDefinition())->projection()
            : null;
        $ctx = new ProjectionContext('p', 'v', '/x/', 'Title', 'en', null, null, $resolver);

        return (new ProjectionBuilder())->build($tree, $ctx)->view(ProjectionView::Public);
    }

    public function test_schema_org_is_structurally_valid(): void
    {
        $public = $this->build(new BlockTree([
            new BlockNode('i1', 'image', ['asset_id' => 'a-1', 'alt' => 'A cat', 'url' => 'https://cdn/x.jpg']),
        ]));

        $schema = $public['schema_org'];
        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertIsArray($schema['@graph']);
        $this->assertNotEmpty($schema['@graph']);

        foreach ($schema['@graph'] as $node) {
            $this->assertArrayHasKey('@type', $node);
            $this->assertContains($node['@type'], self::KNOWN_TYPES, "unknown schema type {$node['@type']}");
            foreach ($node as $key => $value) {
                $this->assertNotNull($value, "null value for {$key}");
            }
        }

        // Round-trips losslessly (valid JSON).
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSame($schema, json_decode($json, true));
    }

    public function test_empty_page_emits_valid_empty_graph(): void
    {
        $public = $this->build(new BlockTree([
            new BlockNode('u1', 'unknown', ['whatever' => 'x']),
        ]));

        $this->assertSame('https://schema.org', $public['schema_org']['@context']);
        $this->assertSame([], $public['schema_org']['@graph']);
        // Valid JSON even when empty.
        $this->assertIsString(json_encode($public, JSON_THROW_ON_ERROR));
    }
}
