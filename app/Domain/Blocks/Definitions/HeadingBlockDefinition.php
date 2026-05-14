<?php

namespace App\Domain\Blocks\Definitions;

class HeadingBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'heading'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', 'in:h1,h2,h3,h4,h5,h6'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
