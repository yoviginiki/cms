<?php

namespace App\Domain\Blocks\Definitions;

class FootnoteBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'footnote'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'content' => ['sometimes', 'string'],
            'marker'  => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,a[href|target]',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
