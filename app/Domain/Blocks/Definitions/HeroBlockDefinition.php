<?php

namespace App\Domain\Blocks\Definitions;

class HeroBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'hero'; }
    public function category(): string { return 'marketing'; }

    public function validationRules(): array
    {
        return [
            // Content fields
            'title'              => ['required', 'string', 'max:255'],
            'subtitle'           => ['sometimes', 'nullable', 'string', 'max:500'],

            // Background fields (BackgroundEditor output)
            'bg_type'            => ['sometimes', 'in:none,color,gradient,image'],
            'bg_color'           => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'bg_gradient_type'   => ['sometimes', 'in:linear,radial'],
            'bg_gradient_angle'  => ['sometimes', 'integer', 'min:0', 'max:360'],
            'bg_gradient_stops'  => ['sometimes', 'array'],
            'bg_gradient_stops.*.color'    => ['required_with:bg_gradient_stops', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_gradient_stops.*.position' => ['required_with:bg_gradient_stops', 'integer', 'min:0', 'max:100'],
            'bg_image'           => ['sometimes', 'nullable', 'string', 'max:2048'],
            'bg_asset_id'        => ['sometimes', 'nullable', 'string', 'max:36'],
            'bg_image_size'      => ['sometimes', 'in:cover,contain,auto'],
            'bg_image_position'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(center|top|bottom|left|right)(\s+(center|top|bottom|left|right))?$/'],
            'bg_image_repeat'    => ['sometimes', 'in:no-repeat,repeat,repeat-x,repeat-y'],
            'bg_overlay_color'   => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_overlay_opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'bg_scroll_effect'   => ['sometimes', 'in:none,fixed,parallax,zoom'],
            'bg_parallax_speed'  => ['sometimes', 'numeric', 'min:0.1', 'max:1'],

            // Layout fields
            'headlineTag'       => ['sometimes', 'in:h1,h2,h3'],
            'textAlignment'     => ['sometimes', 'in:left,center,right'],
            'verticalPosition'  => ['sometimes', 'in:top,center,bottom'],
            'sectionHeight'     => ['sometimes', 'in:auto,sm,md,lg,fullscreen'],
            'contentMaxWidth'   => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],

            // Typography fields
            'headlineSize'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'headlineWeight'    => ['sometimes', 'in:400,500,600,700,800,900'],
            'headlineColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'subheadlineSize'   => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'subtitleColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'adaptiveTextColor' => ['sometimes', 'boolean'],

            // Performance
            'mediaLoading'      => ['sometimes', 'in:eager,lazy'],

            // CTA / Link fields
            'ctaText'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'ctaUrl'             => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|mailto:|tel:|\/|\.\/|\.\.\/#|\?|[a-zA-Z0-9])/i', 'not_regex:/^(javascript|data|vbscript):/i'],

            // CTA button style fields (all optional — backward compatible)
            'ctaVariant'         => ['sometimes', 'in:filled,outline,ghost,link'],
            'ctaSize'            => ['sometimes', 'in:sm,md,lg'],
            'ctaAlign'           => ['sometimes', 'nullable', 'in:,left,center,right'],
            'ctaBgColor'         => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'ctaTextColor'       => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'ctaBorderColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'ctaBorderWidth'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'ctaBorderRadius'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],

            // Responsive overrides (optional — tablet/mobile inherit from desktop)
            'responsive'                        => ['sometimes', 'nullable', 'array'],
            'responsive.tablet'                 => ['sometimes', 'nullable', 'array'],
            'responsive.tablet.textAlignment'   => ['sometimes', 'in:left,center,right'],
            'responsive.tablet.sectionHeight'   => ['sometimes', 'in:auto,sm,md,lg,fullscreen'],
            'responsive.tablet.contentMaxWidth' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'responsive.mobile'                 => ['sometimes', 'nullable', 'array'],
            'responsive.mobile.textAlignment'   => ['sometimes', 'in:left,center,right'],
            'responsive.mobile.sectionHeight'   => ['sometimes', 'in:auto,sm,md,lg,fullscreen'],
            'responsive.mobile.contentMaxWidth' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],

            // Section border & shadow (all optional — backward compatible)
            'sectionBorderWidth'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'sectionBorderColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'sectionBorderStyle'     => ['sometimes', 'nullable', 'in:,solid,dashed,dotted'],
            'sectionBorderRadius'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'sectionShadow'          => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow'],
            'sectionShadowMode'      => ['sometimes', 'in:preset,custom'],
            'sectionShadowCustom'    => ['sometimes', 'nullable', 'array'],
            'sectionShadowCustom.x'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'sectionShadowCustom.y'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'sectionShadowCustom.blur' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'sectionShadowCustom.spread' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'sectionShadowCustom.color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'sectionShadowCustom.opacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sectionShadowCustom.inset' => ['sometimes', 'boolean'],

            // Content box / text readability layer (all optional — backward compatible)
            'contentBoxEnabled'      => ['sometimes', 'boolean'],
            'contentBoxBgColor'      => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'contentBoxOpacity'      => ['sometimes', 'integer', 'min:0', 'max:100'],
            'contentBoxBorderRadius' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxBorderColor'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'contentBoxBorderWidth'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadow'       => ['sometimes', 'nullable', 'in:,sm,md,lg'],
            'contentBoxPadding'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],

            // Accessibility fields
            'alt'                => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
