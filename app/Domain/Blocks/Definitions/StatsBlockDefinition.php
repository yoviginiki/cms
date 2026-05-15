<?php

namespace App\Domain\Blocks\Definitions;

class StatsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'stats'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'           => ['sometimes', 'array'],
            'items.*.value'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.label'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.prefix'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.suffix'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'columns'         => ['sometimes', 'integer', 'min:1', 'max:6'],
            'gap'             => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'cardBgColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'cardBorderRadius'=> ['sometimes', 'nullable'],
            'cardShadow'      => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow,sm,md,lg'],
            'cardShadowMode'  => ['sometimes', 'in:preset,custom'],
            'cardShadowCustom'=> ['sometimes', 'nullable', 'array'],
            'valueColor'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'labelColor'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'valueFontSize'   => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
