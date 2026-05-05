<?php

namespace App\Domain\Blocks\Definitions;

class ScrollPageBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'scroll_page'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'typography' => ['sometimes', 'array'],
            'palette' => ['sometimes', 'array'],
            'layout' => ['sometimes', 'array'],
            'backdrop' => ['sometimes', 'array'],
            'mouseEffect' => ['sometimes', 'array'],
            'reveal' => ['sometimes', 'array'],
            'responsive' => ['sometimes', 'array'],
            'scrollHint' => ['sometimes', 'array'],
            'pages' => ['sometimes', 'array'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
