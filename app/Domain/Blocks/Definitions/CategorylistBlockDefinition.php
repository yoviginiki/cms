<?php

namespace App\Domain\Blocks\Definitions;

class CategorylistBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'categorylist'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'style'      => ['sometimes', 'in:links,badges,cards'],
            'showCount'  => ['sometimes', 'boolean'],
            'parentOnly' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
