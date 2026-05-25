<?php

namespace App\Domain\Blocks\Definitions;

class TestimonialBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'testimonial'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'           => ['sometimes', 'array'],
            'items.*.quote'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items.*.author'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.role'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.avatar'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'layout'          => ['sometimes', 'in:single,grid,carousel'],
            'cardBgColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderRadius'=> ['sometimes', 'nullable'],
            'cardShadow'      => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow,sm,md,lg'],
            'cardShadowMode'  => ['sometimes', 'in:preset,custom'],
            'cardShadowCustom'=> ['sometimes', 'nullable', 'array'],
            'quoteColor'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'authorColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
