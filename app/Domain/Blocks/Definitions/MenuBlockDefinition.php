<?php

namespace App\Domain\Blocks\Definitions;

class MenuBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'menu'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            // Source
            'source'          => ['sometimes', 'in:system,custom'],
            'menuId'          => ['sometimes', 'nullable', 'string', 'max:36'],

            // Custom inline items
            'customItems'           => ['sometimes', 'array'],
            'customItems.*.label'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'customItems.*.url'     => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|mailto:|tel:|\/|\.\/|\.\.\/#|\?|#|[a-zA-Z0-9])/i', 'not_regex:/^(javascript|data|vbscript)\s*:/i'],
            'customItems.*.target'  => ['sometimes', 'in:_self,_blank'],

            // Layout
            'style'            => ['sometimes', 'in:horizontal,vertical,hamburger'],
            'showLogo'         => ['sometimes', 'boolean'],
            'sticky'           => ['sometimes', 'boolean'],
            'mobileBreakpoint' => ['sometimes', 'integer', 'min:0', 'max:1920'],
            'hamburgerIcon'    => ['sometimes', 'nullable', 'string', 'max:20'],

            // Styling
            'bgColor'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'textColor'    => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'hoverColor'   => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'activeColor'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'borderColor'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'fontSize'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'fontWeight'   => ['sometimes', 'nullable', 'in:,400,500,600,700'],
            'padding'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\s.%]+$/'],
            'itemGap'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'borderRadius' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'logoSize'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
