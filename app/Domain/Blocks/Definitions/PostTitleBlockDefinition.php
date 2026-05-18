<?php

namespace App\Domain\Blocks\Definitions;

class PostTitleBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-title'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'tag' => ['sometimes', 'in:h1,h2,h3,h4,h5,h6'],
            'fontSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'fontWeight' => ['sometimes', 'nullable', 'in:,400,500,600,700,800'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right'],
            'textShadow' => ['sometimes', 'nullable', 'in:,sm,md,lg,outline,glow']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
