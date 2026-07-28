<?php

namespace App\Domain\Blocks\Definitions;

class ButtonBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'button'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'style' => ['sometimes', 'in:primary,secondary,outline,ghost'],
            'size' => ['sometimes', 'in:sm,md,lg'],
            'bgColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\))$/'],
            'textColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\))$/'],
            'fontSize' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'fontWeight' => ['sometimes', 'nullable', 'in:400,500,600,700,800,900'],
            'target' => ['sometimes', 'in:_self,_blank'],
            'icon' => ['sometimes', 'nullable', 'string'],
        ] + \App\Support\Blocks\SliderAnimation::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
