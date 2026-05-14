<?php

namespace App\Domain\Blocks\Definitions;

class IconBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'icon'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'name'            => ['sometimes', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\-_]+$/'],
            'size'            => ['sometimes', 'in:sm,md,lg,xl'],
            'color'           => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'background'      => ['sometimes', 'in:none,circle,square,rounded'],
            'backgroundColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
