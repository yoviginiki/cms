<?php

namespace App\Domain\Blocks\Definitions;

use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Descriptors\FieldType;

class HeadingBlockDefinition implements BlockDefinition, ProvidesProjection
{
    public function type(): string { return 'heading'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text'          => ['sometimes', 'string', 'max:255'],
            'level'         => ['sometimes', 'in:h1,h2,h3,h4,h5,h6'],
            'color'         => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'fontSize'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'fontWeight'    => ['sometimes', 'nullable', 'in:,400,500,600,700,800,900'],
            'lineHeight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)?$/'],
            'letterSpacing' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'textTransform' => ['sometimes', 'nullable', 'in:,uppercase,lowercase,capitalize'],
            'textAlign'     => ['sometimes', 'nullable', 'in:,left,center,right'],
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
        // A heading carries no standalone schema.org @type; it structures the
        // page's heading outline and provides searchable text for RAG.
        return BlockProjection::make()
            ->schemaType(null)
            ->field('text', 'headline', FieldType::Text, [
                'rag' => true,
            ])
            ->headingLevel(fn (array $data): ?int => match ($data['level'] ?? 'h2') {
                'h1' => 1,
                'h2' => 2,
                'h3' => 3,
                'h4' => 4,
                'h5' => 5,
                'h6' => 6,
                default => null,
            });
    }
}
