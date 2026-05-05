<?php

namespace App\Domain\Blocks\Definitions;

class CodeBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'code'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'code' => ['required', 'string'],
            'language' => ['sometimes', 'string', 'max:30'],
            'show_line_numbers' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => '',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
