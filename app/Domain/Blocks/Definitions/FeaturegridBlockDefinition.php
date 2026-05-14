<?php

namespace App\Domain\Blocks\Definitions;

class FeaturegridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'featuregrid'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'               => ['sometimes', 'array'],
            'items.*.icon'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'columns'             => ['sometimes', 'integer', 'min:1', 'max:6'],
            'style'               => ['sometimes', 'in:icon-top,icon-left'],
            'gap'                 => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            // Card styling
            'cardBgColor'         => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderWidth'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'cardBorderRadius'    => ['sometimes', 'nullable'],
            'cardPadding'         => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'cardShadow'          => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow,sm,md,lg'],
            'cardShadowMode'      => ['sometimes', 'in:preset,custom'],
            'cardShadowCustom'    => ['sometimes', 'nullable', 'array'],
            'cardShadowCustom.x'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'cardShadowCustom.y'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'cardShadowCustom.blur' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'cardShadowCustom.spread' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'cardShadowCustom.color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardShadowCustom.opacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'cardShadowCustom.inset' => ['sometimes', 'boolean'],
            // Typography
            'titleColor'          => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'descColor'           => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'iconSize'            => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'iconColor'           => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
