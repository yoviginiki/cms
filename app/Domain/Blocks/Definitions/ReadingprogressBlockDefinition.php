<?php

namespace App\Domain\Blocks\Definitions;

class ReadingprogressBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'readingprogress'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'style'  => ['sometimes', 'in:top-bar,circular,side-bar'],
            'color'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'height' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
