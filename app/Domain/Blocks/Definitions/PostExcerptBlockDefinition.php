<?php

namespace App\Domain\Blocks\Definitions;

class PostExcerptBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-excerpt'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'fontSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right'],
            'maxLines' => ['sometimes', 'integer', 'min:0', 'max:20']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
