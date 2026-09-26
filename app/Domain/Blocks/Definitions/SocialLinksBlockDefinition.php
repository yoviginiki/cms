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
            'links' => ['sometimes', 'array', 'max:20'],
            'links.*.network' => ['required', 'in:' . implode(',', array_keys(\App\Support\Blocks\SocialIcons::NETWORKS))],
            // Empty while being filled in (the editor starts rows blank) — empty
            // links are simply not published.
            'links.*.url' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Own icon (image from Media) instead of the built-in one
            'links.*.icon' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(/|https?://)#i'],
            'links.*.label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'style' => ['sometimes', 'in:icon,circle,square,text'],
            'size' => ['sometimes', 'in:sm,md,lg'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'showLabels' => ['sometimes', 'boolean'],
            'align' => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
