<?php

namespace App\Domain\Blocks\Definitions;

class CategoryHeaderBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'category-header'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'showDescription' => ['sometimes', 'boolean'],
            'showPostCount' => ['sometimes', 'boolean'],
            'titleTag' => ['sometimes', 'in:h1,h2,h3'],
            'titleSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'titleColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'textAlign' => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
