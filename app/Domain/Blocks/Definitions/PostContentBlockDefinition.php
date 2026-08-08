<?php

namespace App\Domain\Blocks\Definitions;

use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;

class PostContentBlockDefinition implements BlockDefinition, ProvidesProjection
{
    public function type(): string { return 'post-content'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }

    public function projection(): ?BlockProjection
    {
        // post-content is a DYNAMIC SLOT: its Blade renders the ambient Post's
        // body (`$__postContentHtml`) and it holds no fields of its own in
        // blocks.data. The article body is projected at the post's constituent
        // blocks (heading / rich-text / image), so this slot deliberately
        // emits nothing of its own — projecting it here would double-count the
        // body and break parity + segment-hash determinism.
        //
        // It opts into the contract explicitly (rather than staying silent) to
        // record that the block was considered and is intentionally inert.
        // The Article @type / headline / datePublished live at the page/post
        // level and remain owned by StructuredDataService (Gate 0 decision 2).
        return BlockProjection::make()->schemaType(null);
    }
}
