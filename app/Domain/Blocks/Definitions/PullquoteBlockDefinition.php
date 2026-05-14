<?php

namespace App\Domain\Blocks\Definitions;

class PullquoteBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'pullquote'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'text'        => ['sometimes', 'string', 'max:2000'],
            'attribution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'style'       => ['sometimes', 'in:large-text,border-left,centered'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
