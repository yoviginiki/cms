<?php

namespace App\Domain\Blocks\Definitions;

class QuoteBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'quote'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'content' => ['required', 'string'],
            'citation' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,a[href|target]',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
