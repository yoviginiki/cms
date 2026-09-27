<?php

namespace App\Domain\Blocks\Definitions;

/** Social network links with line icons (grid areas: footer/header). */
class SocialLinksBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'social-links'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'links' => ['sometimes', 'array', 'max:50'],
            'links.*.network' => ['required', 'in:' . implode(',', array_keys(\App\Support\Blocks\SocialIcons::NETWORKS))],
            // Empty while being filled in (the editor starts rows blank) — empty
            // links are simply not published.
            'links.*.url' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Own icon (image from Media) instead of the built-in one
            'links.*.icon' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(/|https?://)#i'],
            'links.*.label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'style' => ['sometimes', 'in:icon,circle,square,text'],
            'size' => ['sometimes', 'in:sm,md,lg'],
            // Exact icon size in px — overrides the size preset when set
            'iconSize' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:160'],
            'gap' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'showLabels' => ['sometimes', 'boolean'],
            // Background of the circle/square: '' = theme default, 'none' = off, or a color
            'iconBg' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(none|#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\))$/'],
            'shadow' => ['sometimes', 'nullable', 'in:none,sm,md,lg'],
            'align' => ['sometimes', 'in:left,center,right'],
            // Optional title ("Follow Us") — part of the block so it moves/styles with it
            'title' => ['sometimes', 'nullable', 'array'],
            'title.show' => ['sometimes', 'boolean'],
            'title.text' => ['sometimes', 'nullable', 'string', 'max:120'],
            'title.position' => ['sometimes', 'in:above,below,before,after'],
            'title.tag' => ['sometimes', 'in:h2,h3,h4,h5,h6,p,span'],
            'title.fontSize' => ['sometimes', 'nullable', 'integer', 'min:8', 'max:120'],
            'title.fontFamily' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9 ,\'"()\-]+$/'],
            'title.fontWeight' => ['sometimes', 'nullable', 'in:300,400,500,600,700,800,900'],
            'title.fontStyle' => ['sometimes', 'nullable', 'in:normal,italic'],
            'title.textTransform' => ['sometimes', 'nullable', 'in:none,uppercase,lowercase,capitalize'],
            'title.letterSpacing' => ['sometimes', 'nullable', 'numeric', 'min:-5', 'max:20'],
            'title.color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'title.padding' => ['sometimes', 'nullable', 'array'],
            'title.padding.*' => ['nullable', 'integer', 'min:0', 'max:200'],
            'title.gap' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
