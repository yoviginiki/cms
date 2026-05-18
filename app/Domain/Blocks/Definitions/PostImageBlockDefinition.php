<?php

namespace App\Domain\Blocks\Definitions;

class PostImageBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-image'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'size' => ['sometimes', 'in:full,large,medium,thumbnail'],
            'aspectRatio' => ['sometimes', 'nullable', 'string', 'max:10'],
            'borderRadius' => ['sometimes', 'nullable', 'string', 'max:20'],
            'objectFit' => ['sometimes', 'in:cover,contain,fill,none']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
