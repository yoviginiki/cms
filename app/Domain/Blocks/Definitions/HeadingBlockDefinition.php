<?php

namespace App\Domain\Blocks\Definitions;

class HeadingBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'heading'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text'          => ['sometimes', 'string', 'max:255'],
            'level'         => ['sometimes', 'in:h1,h2,h3,h4,h5,h6'],
            'color'         => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'fontSize'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'fontWeight'    => ['sometimes', 'nullable', 'in:,400,500,600,700,800,900'],
            'lineHeight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)?$/'],
            'letterSpacing' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'textTransform' => ['sometimes', 'nullable', 'in:,uppercase,lowercase,capitalize'],
            'textAlign'     => ['sometimes', 'nullable', 'in:,left,center,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
