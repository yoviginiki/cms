<?php

namespace App\Domain\Blocks\Definitions;

class PostNavigationBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-navigation'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'showLabels' => ['sometimes', 'boolean'],
            'style' => ['sometimes', 'in:minimal,buttons,full']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
