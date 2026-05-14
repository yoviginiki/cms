<?php

namespace App\Domain\Blocks\Definitions;

class MenuBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'menu'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'menuId'           => ['sometimes', 'nullable', 'string', 'max:36'],
            'style'            => ['sometimes', 'in:horizontal,vertical'],
            'showLogo'         => ['sometimes', 'boolean'],
            'sticky'           => ['sometimes', 'boolean'],
            'mobileBreakpoint' => ['sometimes', 'integer', 'min:0', 'max:1920'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
