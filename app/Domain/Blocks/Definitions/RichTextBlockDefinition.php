<?php

namespace App\Domain\Blocks\Definitions;

use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Descriptors\FieldType;

class RichTextBlockDefinition implements BlockDefinition, ProvidesProjection
{
    public function type(): string { return 'rich-text'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'content' => ['sometimes', 'string'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,a[href|target],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre,table,thead,tbody,tr,th,td,img[src|alt]',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }

    public function projection(): ?BlockProjection
    {
        // Rich body content: a self-contained RAG segment. The RichText type
        // tells the builder to HTML-parse the value for outbound links, inline
        // assets and word count (inventory), so no separate flag is needed.
        return BlockProjection::make()
            ->schemaType(null)
            ->field('content', 'text', FieldType::RichText, [
                'rag' => true,
                'segment' => true,
            ])
            ->segmentBoundary(true);
    }
}
