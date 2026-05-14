<?php

namespace App\Domain\Blocks\Definitions;

class AnchormenuBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'anchormenu'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'items'           => ['sometimes', 'array'],
            'items.*.label'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.anchor'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'style'           => ['sometimes', 'in:horizontal,vertical,pills'],
            'sticky'          => ['sometimes', 'boolean'],
            'smooth'          => ['sometimes', 'boolean'],
            'offset'          => ['sometimes', 'integer', 'min:0', 'max:500'],
            'activeHighlight' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
