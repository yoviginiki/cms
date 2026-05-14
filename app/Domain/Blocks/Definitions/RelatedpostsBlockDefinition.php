<?php

namespace App\Domain\Blocks\Definitions;

class RelatedpostsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'relatedposts'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'limit'   => ['sometimes', 'integer', 'min:1', 'max:20'],
            'basedOn' => ['sometimes', 'in:category,manual'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
