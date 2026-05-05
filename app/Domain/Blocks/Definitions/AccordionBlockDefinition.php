<?php

namespace App\Domain\Blocks\Definitions;

class AccordionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'accordion'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.content' => ['required', 'string'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,a[href|target],ul,ol,li',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
