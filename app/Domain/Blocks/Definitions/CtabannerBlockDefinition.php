<?php

namespace App\Domain\Blocks\Definitions;

class CtabannerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'ctabanner'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'heading'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'text'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'buttonText'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'buttonUrl'       => ['sometimes', 'nullable', 'string', 'max:2048'],
            'backgroundStyle' => ['sometimes', 'in:solid,gradient,image'],
            'backgroundColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'backgroundImage' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
