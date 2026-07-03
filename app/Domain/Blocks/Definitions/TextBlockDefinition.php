<?php

namespace App\Domain\Blocks\Definitions;

class TextBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'text'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'content'       => ['sometimes', 'string'],
            'textAlign'     => ['sometimes', 'nullable', 'in:,left,center,right,justify'],
            'textColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'fontSize'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'fontWeight'    => ['sometimes', 'nullable', 'in:,300,400,500,600,700'],
            'fontStyle'     => ['sometimes', 'nullable', 'in:,italic'],
            'lineHeight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)?$/'],
            'letterSpacing' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
        ] + \App\Support\Blocks\SliderAnimation::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,a[href|target],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
