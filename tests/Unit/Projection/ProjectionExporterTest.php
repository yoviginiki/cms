<?php

namespace Tests\Unit\Projection;

use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Blocks\Definitions\RichTextBlockDefinition;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Export\ProjectionExporter;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Domain\Projection\Projection;
use App\Domain\Projection\ProjectionBuilder;
use App\Domain\Projection\ProjectionContext;
use Tests\TestCase;

class ProjectionExporterTest extends TestCase
{
    private function projection(): Projection
    {
        $map = [
            'heading' => (new HeadingBlockDefinition())->projection(),
            'rich-text' => (new RichTextBlockDefinition())->projection(),
            'image' => (new ImageBlockDefinition())->projection(),
        ];
        $resolver = fn (string $t): ?BlockProjection => $map[$t] ?? null;
        $ctx = new ProjectionContext('p', 'v', '/doc/', 'Doc Title', 'en', null, null, $resolver);

        $tree = new BlockTree([
            new BlockNode('h1', 'heading', ['level' => 'h2', 'text' => 'Chapter One']),
            new BlockNode('r1', 'rich-text', ['content' => '<p>Body <strong>text</strong> here.</p>']),
            new BlockNode('sec', 'section', [], [
                new BlockNode('i1', 'image', ['url' => 'https://x/c.jpg', 'alt' => 'A cat']),
            ]),
        ]);

        return (new ProjectionBuilder())->build($tree, $ctx);
    }

    public function test_json_export_is_lossless_and_valid(): void
    {
        $exporter = new ProjectionExporter();
        $json = $exporter->toJson($this->projection());

        $decoded = json_decode($json, true);
        $this->assertSame($this->projection()->toArray(), $decoded);
        $this->assertSame('1.0', $decoded['projection_version']);
    }

    public function test_markdown_export_renders_document_order(): void
    {
        $md = (new ProjectionExporter())->toMarkdown($this->projection());

        $expected = <<<MD
        # Doc Title

        ## Chapter One

        Body text here.

        ![A cat](https://x/c.jpg)

        MD;

        $this->assertSame($expected, $md);
    }

    public function test_markdown_is_deterministic(): void
    {
        $exporter = new ProjectionExporter();
        $this->assertSame(
            $exporter->toMarkdown($this->projection()),
            $exporter->toMarkdown($this->projection()),
        );
    }
}
