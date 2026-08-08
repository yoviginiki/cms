<?php

namespace Tests\Unit\Projection;

use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use App\Domain\Blocks\Definitions\ImageBlockDefinition;
use App\Domain\Blocks\Definitions\ParagraphBlockDefinition;
use App\Domain\Blocks\Definitions\PostContentBlockDefinition;
use App\Domain\Blocks\Definitions\RichTextBlockDefinition;
use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Descriptors\FieldType;
use Tests\TestCase;

class BlockProjectionContractTest extends TestCase
{
    public function test_heading_declares_headline_and_heading_level(): void
    {
        $def = new HeadingBlockDefinition();
        $this->assertInstanceOf(ProvidesProjection::class, $def);

        $proj = $def->projection();
        $this->assertInstanceOf(BlockProjection::class, $proj);
        $this->assertNull($proj->getSchemaType());

        $fields = $proj->getFields();
        $this->assertCount(1, $fields);
        $this->assertSame('text', $fields[0]->path);
        $this->assertSame('headline', $fields[0]->name);
        $this->assertSame(FieldType::Text, $fields[0]->type);
        $this->assertTrue($fields[0]->rag);
        $this->assertFalse($fields[0]->schema);

        $this->assertTrue($proj->hasHeadingLevel());
        $this->assertSame(2, $proj->resolveHeadingLevel(['level' => 'h2']));
        $this->assertSame(4, $proj->resolveHeadingLevel(['level' => 'h4']));
        $this->assertSame(2, $proj->resolveHeadingLevel([])); // default h2
        $this->assertNull($proj->resolveHeadingLevel(['level' => 'nonsense']));
    }

    public function test_rich_text_is_a_segment_boundary(): void
    {
        $def = new RichTextBlockDefinition();
        $this->assertInstanceOf(ProvidesProjection::class, $def);

        $proj = $def->projection();
        $this->assertTrue($proj->isSegmentBoundary());

        $fields = $proj->getFields();
        $this->assertCount(1, $fields);
        $this->assertSame('content', $fields[0]->path);
        $this->assertSame(FieldType::RichText, $fields[0]->type);
        $this->assertTrue($fields[0]->rag);
        $this->assertTrue($fields[0]->segment);
        $this->assertFalse($proj->hasHeadingLevel());
    }

    public function test_image_declares_asset_and_schema_fields(): void
    {
        $def = new ImageBlockDefinition();
        $proj = $def->projection();

        $this->assertSame('ImageObject', $proj->getSchemaType());

        $paths = array_map(fn ($f) => $f->path, $proj->getFields());
        $this->assertSame(['asset_id', 'assetId', 'url', 'alt', 'caption'], $paths);

        foreach ($proj->getFields() as $field) {
            $this->assertTrue($field->schema, "field {$field->path} should feed schema");
        }
    }

    public function test_post_content_opts_in_but_is_inert(): void
    {
        // The dynamic slot opts into the contract but emits nothing of its own
        // (its body is projected at the post's constituent blocks).
        $def = new PostContentBlockDefinition();
        $this->assertInstanceOf(ProvidesProjection::class, $def);

        $proj = $def->projection();
        $this->assertInstanceOf(BlockProjection::class, $proj);
        $this->assertNull($proj->getSchemaType());
        $this->assertSame([], $proj->getFields());
        $this->assertFalse($proj->isSegmentBoundary());
        $this->assertFalse($proj->hasHeadingLevel());
    }

    public function test_non_pilot_block_does_not_participate(): void
    {
        // A block that has not opted in must not implement the contract at all.
        $def = new ParagraphBlockDefinition();
        $this->assertNotInstanceOf(ProvidesProjection::class, $def);
    }

    public function test_descriptor_defaults_are_empty_and_inert(): void
    {
        $proj = BlockProjection::make();
        $this->assertNull($proj->getSchemaType());
        $this->assertSame([], $proj->getFields());
        $this->assertFalse($proj->isSegmentBoundary());
        $this->assertFalse($proj->hasHeadingLevel());
        $this->assertNull($proj->resolveHeadingLevel(['level' => 'h1']));
    }
}
