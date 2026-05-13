<?php

namespace App\Domain\Blocks\Definitions;

class HeroBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'hero'; }
    public function category(): string { return 'marketing'; }

    /** Allowed keys for per-side and per-corner objects. */
    private const SPACING_KEYS = ['top', 'right', 'bottom', 'left'];
    private const CORNER_KEYS = ['topLeft', 'topRight', 'bottomRight', 'bottomLeft'];

    /** Validate a field that accepts either a safe CSS dimension string or an array (per-side/per-corner). */
    private static function dimOrArray(string $pattern = '/^\d+(\.\d+)?(px|rem|em|%)$/'): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($pattern) {
            if (is_null($value) || $value === '') return;
            if (is_array($value)) {
                $allowed = array_merge(self::SPACING_KEYS, self::CORNER_KEYS);
                foreach (array_keys($value) as $key) {
                    if (!in_array($key, $allowed, true)) {
                        $fail("The {$attribute} contains an invalid key: {$key}.");
                        return;
                    }
                }
                return; // sub-key values validated separately by dot-notation rules
            }
            if (!is_string($value) || strlen($value) > 20 || !preg_match($pattern, $value)) {
                $fail("The {$attribute} must be a valid CSS dimension or a per-side/per-corner object.");
            }
        };
    }

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
            'headlineLineHeight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)?$/'],
            'headlineLetterSpacing' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'headlineTextTransform' => ['sometimes', 'nullable', 'in:,uppercase,lowercase,capitalize'],
            'subheadlineSize'   => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/'],
            'subheadlineWeight' => ['sometimes', 'in:400,500,600,700,800,900'],
            'subtitleColor'     => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'subheadlineLineHeight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)?$/'],
            'subheadlineLetterSpacing' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'subheadlineTextTransform' => ['sometimes', 'nullable', 'in:,uppercase,lowercase,capitalize'],
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
            // CTA border radius: string (legacy) or per-corner object
            'ctaBorderRadius'                => ['sometimes', 'nullable', self::dimOrArray()],
            'ctaBorderRadius.topLeft'        => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'ctaBorderRadius.topRight'       => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'ctaBorderRadius.bottomRight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'ctaBorderRadius.bottomLeft'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],

            // CTA shadow (all optional — backward compatible)
            'ctaShadow'          => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow,sm,md,lg'],
            'ctaShadowMode'      => ['sometimes', 'in:preset,custom'],
            'ctaShadowCustom'    => ['sometimes', 'nullable', 'array'],
            'ctaShadowCustom.x'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'ctaShadowCustom.y'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'ctaShadowCustom.blur' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'ctaShadowCustom.spread' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'ctaShadowCustom.color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'ctaShadowCustom.opacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'ctaShadowCustom.inset' => ['sometimes', 'boolean'],

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
            // Section border radius: string (legacy) or per-corner object
            'sectionBorderRadius'                => ['sometimes', 'nullable', self::dimOrArray()],
            'sectionBorderRadius.topLeft'        => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'sectionBorderRadius.topRight'       => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'sectionBorderRadius.bottomRight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'sectionBorderRadius.bottomLeft'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
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
            // Content box border radius: string (legacy) or per-corner object
            'contentBoxBorderRadius'                => ['sometimes', 'nullable', self::dimOrArray()],
            'contentBoxBorderRadius.topLeft'        => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxBorderRadius.topRight'       => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxBorderRadius.bottomRight'    => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxBorderRadius.bottomLeft'     => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxBorderColor'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'contentBoxBorderWidth'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadow'       => ['sometimes', 'nullable', 'in:,none,subtle,medium,large,glow,sm,md,lg'],
            'contentBoxShadowMode'   => ['sometimes', 'in:preset,custom'],
            'contentBoxShadowCustom' => ['sometimes', 'nullable', 'array'],
            'contentBoxShadowCustom.x'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadowCustom.y'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadowCustom.blur' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadowCustom.spread' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em)$/'],
            'contentBoxShadowCustom.color' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'contentBoxShadowCustom.opacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'contentBoxShadowCustom.inset' => ['sometimes', 'boolean'],
            // Content box padding: string (legacy) or per-side object
            'contentBoxPadding'              => ['sometimes', 'nullable', self::dimOrArray()],
            'contentBoxPadding.top'          => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxPadding.right'        => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxPadding.bottom'       => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'contentBoxPadding.left'         => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],

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
