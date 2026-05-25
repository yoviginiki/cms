<?php

namespace App\Support\Blocks;

/**
 * BLOCK-EFFECTS-1 — Global Card/Image Hover Effects + Image Filters.
 *
 * Safe CSS builders for card hover, image filters, and overlays.
 * Parity with resources/admin/src/lib/blockEffects.ts.
 */
class BlockEffects
{
    // ═══════════════════════════════════════
    // Hover presets (must match TS HOVER_PRESETS)
    // ═══════════════════════════════════════

    private const HOVER_PRESETS = [
        'none' => ['scale' => 1, 'translateY' => 0, 'shadow' => 'none'],
        'lift' => ['scale' => 1, 'translateY' => -6, 'shadow' => 'medium'],
        'scale' => ['scale' => 1.03, 'translateY' => 0, 'shadow' => 'soft'],
        'lift-scale' => ['scale' => 1.02, 'translateY' => -4, 'shadow' => 'medium'],
        'soft-pop' => ['scale' => 1.02, 'translateY' => -3, 'shadow' => 'soft'],
        'strong-pop' => ['scale' => 1.05, 'translateY' => -8, 'shadow' => 'strong'],
    ];

    private const SHADOW_VALUES = [
        'none' => 'none',
        'soft' => '0 4px 12px rgba(0,0,0,0.08)',
        'medium' => '0 8px 24px rgba(0,0,0,0.12)',
        'strong' => '0 16px 40px rgba(0,0,0,0.18)',
    ];

    private const FILTER_PRESETS = [
        'none' => ['grayscale' => 0, 'sepia' => 0, 'brightness' => 100, 'contrast' => 100, 'saturation' => 100],
        'grayscale' => ['grayscale' => 100, 'sepia' => 0, 'brightness' => 100, 'contrast' => 100, 'saturation' => 100],
        'sepia' => ['grayscale' => 0, 'sepia' => 80, 'brightness' => 100, 'contrast' => 100, 'saturation' => 100],
        'muted' => ['grayscale' => 0, 'sepia' => 0, 'brightness' => 95, 'contrast' => 90, 'saturation' => 60],
        'high-contrast' => ['grayscale' => 0, 'sepia' => 0, 'brightness' => 105, 'contrast' => 130, 'saturation' => 110],
        'custom' => ['grayscale' => 0, 'sepia' => 0, 'brightness' => 100, 'contrast' => 100, 'saturation' => 100],
    ];

    private const BLEND_MODES = ['normal', 'multiply', 'screen', 'overlay', 'soft-light'];

    private const REVEAL_MODES = ['none', 'fade', 'reveal-left', 'reveal-right', 'reveal-top', 'reveal-bottom', 'circle', 'diagonal'];

    private const REVEAL_CLIP_DEFAULT = [
        'reveal-left' => 'inset(0 0 0 0)', 'reveal-right' => 'inset(0 0 0 0)',
        'reveal-top' => 'inset(0 0 0 0)', 'reveal-bottom' => 'inset(0 0 0 0)',
        'circle' => 'circle(100% at 50% 50%)', 'diagonal' => 'polygon(0 0, 100% 0, 100% 100%, 0 100%)',
    ];

    private const REVEAL_CLIP_HOVER = [
        'reveal-left' => 'inset(0 100% 0 0)', 'reveal-right' => 'inset(0 0 0 100%)',
        'reveal-top' => 'inset(100% 0 0 0)', 'reveal-bottom' => 'inset(0 0 100% 0)',
        'circle' => 'circle(0% at 50% 50%)', 'diagonal' => 'polygon(0 0, 0 0, 0 100%, 0 100%)',
    ];

    // ═══════════════════════════════════════
    // Public API
    // ═══════════════════════════════════════

    /**
     * Check if card effects are enabled.
     */
    public static function isEnabled(array $data): bool
    {
        return !empty($data['effects']['enabled']);
    }

    /**
     * Build card wrapper inline style (base state with transition).
     * Apply this to each card/article element.
     */
    public static function cardBaseStyle(array $data): string
    {
        $effects = $data['effects'] ?? [];
        if (empty($effects['enabled']) || empty($effects['hover']['enabled'])) return '';

        $hover = $effects['hover'];
        $duration = max(100, min(1000, intval($hover['duration'] ?? 300)));
        $rawEasing = $hover['easing'] ?? 'ease-out';
        $easing = in_array($rawEasing, ['ease', 'ease-out', 'ease-in-out']) ? $rawEasing : 'ease-out';

        return "transition:transform {$duration}ms {$easing},box-shadow {$duration}ms {$easing};";
    }

    /**
     * Build card wrapper hover CSS (transform + shadow).
     * Returns a scoped CSS rule. Pass a unique scope class.
     */
    public static function cardHoverCss(array $data, string $scopeClass): string
    {
        $effects = $data['effects'] ?? [];
        if (empty($effects['enabled']) || empty($effects['hover']['enabled'])) return '';

        $hover = $effects['hover'];
        $preset = self::HOVER_PRESETS[$hover['preset'] ?? 'soft-pop'] ?? self::HOVER_PRESETS['soft-pop'];
        $scale = max(1.0, min(1.2, floatval($hover['scale'] ?? $preset['scale'])));
        $translateY = max(-40, min(0, intval($hover['translateY'] ?? $preset['translateY'])));
        $rawShadow = $hover['shadow'] ?? $preset['shadow'];
        $shadowKey = in_array($rawShadow, array_keys(self::SHADOW_VALUES)) ? $rawShadow : 'none';
        $shadow = self::SHADOW_VALUES[$shadowKey];

        $transforms = [];
        if ($translateY !== 0) $transforms[] = "translateY({$translateY}px)";
        if ($scale !== 1.0) $transforms[] = "scale({$scale})";
        $transform = count($transforms) > 0 ? implode(' ', $transforms) : 'none';

        $rules = [];
        if ($transform !== 'none') $rules[] = "transform:{$transform}";
        if ($shadow !== 'none') $rules[] = "box-shadow:{$shadow}";

        if (empty($rules)) return '';

        $css = ".{$scopeClass}:hover{" . implode(';', $rules) . "}";
        // Respect prefers-reduced-motion
        $css .= "@media(prefers-reduced-motion:reduce){.{$scopeClass}{transition:none!important}.{$scopeClass}:hover{transform:none!important}}";
        return $css;
    }

    /**
     * Build image filter CSS string.
     * Apply as style="filter:{result}" on image elements.
     */
    public static function imageFilterStyle(array $data): string
    {
        $effects = $data['effects'] ?? [];
        if (empty($effects['enabled']) || empty($effects['imageFilter']['enabled'])) return '';

        $filter = $effects['imageFilter'];
        $filterPreset = $filter['preset'] ?? 'none';
        $preset = self::FILTER_PRESETS[$filterPreset] ?? self::FILTER_PRESETS['none'];
        $isCustom = $filterPreset === 'custom';

        $gs = $isCustom ? max(0, min(100, intval($filter['grayscale'] ?? 0))) : $preset['grayscale'];
        $sp = $isCustom ? max(0, min(100, intval($filter['sepia'] ?? 0))) : $preset['sepia'];
        $br = $isCustom ? max(50, min(200, intval($filter['brightness'] ?? 100))) : $preset['brightness'];
        $ct = $isCustom ? max(50, min(200, intval($filter['contrast'] ?? 100))) : $preset['contrast'];
        $st = $isCustom ? max(0, min(200, intval($filter['saturation'] ?? 100))) : $preset['saturation'];

        $parts = [];
        if ($gs > 0) $parts[] = "grayscale({$gs}%)";
        if ($sp > 0) $parts[] = "sepia({$sp}%)";
        if ($br !== 100) $parts[] = "brightness({$br}%)";
        if ($ct !== 100) $parts[] = "contrast({$ct}%)";
        if ($st !== 100) $parts[] = "saturate({$st}%)";

        return count($parts) > 0 ? 'filter:' . implode(' ', $parts) . ';' : '';
    }

    /**
     * Build overlay HTML div.
     * Place inside a position:relative container over the image.
     */
    public static function overlayHtml(array $data): string
    {
        $effects = $data['effects'] ?? [];
        if (empty($effects['enabled']) || empty($effects['overlay']['enabled'])) return '';

        $overlay = $effects['overlay'];
        $color = self::safeColor($overlay['color'] ?? '#000000');
        $opacity = max(0, min(100, intval($overlay['opacity'] ?? 30))) / 100;
        $rawBlend = $overlay['blendMode'] ?? 'normal';
        $blend = in_array($rawBlend, self::BLEND_MODES) ? $rawBlend : 'normal';

        return "<div style=\"position:absolute;inset:0;background-color:{$color};opacity:{$opacity};mix-blend-mode:{$blend};pointer-events:none;border-radius:inherit;\"></div>";
    }

    // ═══════════════════════════════════════
    // Image Hover Reveal
    // ═══════════════════════════════════════

    /**
     * Check if image hover reveal is active.
     */
    public static function isRevealEnabled(array $data): bool
    {
        $effects = $data['effects'] ?? [];
        return !empty($effects['enabled'])
            && !empty($effects['imageHoverReveal']['enabled'])
            && ($effects['imageHoverReveal']['mode'] ?? 'none') !== 'none'
            && !empty($effects['imageFilter']['enabled']);
    }

    /**
     * Build scoped CSS for image hover reveal.
     * The filtered image layer sits on top and clips/fades away on hover.
     */
    public static function revealCss(array $data, string $scopeClass): string
    {
        if (!self::isRevealEnabled($data)) return '';

        $reveal = $data['effects']['imageHoverReveal'];
        $mode = in_array($reveal['mode'] ?? 'fade', self::REVEAL_MODES) ? $reveal['mode'] : 'fade';
        $duration = max(150, min(1500, intval($reveal['duration'] ?? 500)));
        $rawEasing = $reveal['easing'] ?? 'ease-out';
        $easing = in_array($rawEasing, ['ease', 'ease-out', 'ease-in-out']) ? $rawEasing : 'ease-out';

        $filterStyle = self::imageFilterStyle($data);
        $clipDefault = self::REVEAL_CLIP_DEFAULT[$mode] ?? '';
        $clipHover = self::REVEAL_CLIP_HOVER[$mode] ?? '';

        $css = '';

        // Filtered layer base state
        $filteredBase = "position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;z-index:1;{$filterStyle}";
        if ($mode === 'fade') {
            $filteredBase .= "opacity:1;transition:opacity {$duration}ms {$easing};";
        } else {
            $filteredBase .= "clip-path:{$clipDefault};transition:clip-path {$duration}ms {$easing},opacity {$duration}ms {$easing};";
        }
        $css .= ".{$scopeClass} .img-reveal-filtered{" . $filteredBase . "}";

        // Filtered layer hover state
        if ($mode === 'fade') {
            $css .= ".{$scopeClass}:hover .img-reveal-filtered{opacity:0}";
        } else {
            $css .= ".{$scopeClass}:hover .img-reveal-filtered{clip-path:{$clipHover}}";
        }

        // Reduced motion
        $css .= "@media(prefers-reduced-motion:reduce){.{$scopeClass} .img-reveal-filtered{transition:none!important}}";

        return $css;
    }

    /**
     * Render the layered reveal image HTML.
     * Original image below, filtered duplicate on top.
     */
    public static function revealImageHtml(string $src, string $alt, string $extraStyle = ''): string
    {
        $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $safeAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $safeSrc . '" alt="' . $safeAlt . '" style="width:100%;height:100%;object-fit:cover;' . $extraStyle . '" />'
             . '<img src="' . $safeSrc . '" alt="" aria-hidden="true" class="img-reveal-filtered" />';
    }

    // ═══════════════════════════════════════
    // Validation rules (for PHP block definitions)
    // ═══════════════════════════════════════

    /**
     * Get Laravel validation rules for the effects schema.
     * Merge into block's validationRules() return array.
     */
    public static function validationRules(): array
    {
        return [
            'effects'                        => ['sometimes', 'array'],
            'effects.enabled'                => ['sometimes', 'boolean'],
            'effects.hover'                  => ['sometimes', 'array'],
            'effects.hover.enabled'          => ['sometimes', 'boolean'],
            'effects.hover.preset'           => ['sometimes', 'in:none,lift,scale,lift-scale,soft-pop,strong-pop'],
            'effects.hover.scale'            => ['sometimes', 'numeric', 'min:1', 'max:1.2'],
            'effects.hover.translateY'       => ['sometimes', 'integer', 'min:-40', 'max:0'],
            'effects.hover.shadow'           => ['sometimes', 'in:none,soft,medium,strong'],
            'effects.hover.duration'         => ['sometimes', 'integer', 'min:100', 'max:1000'],
            'effects.hover.easing'           => ['sometimes', 'in:ease,ease-out,ease-in-out'],
            'effects.imageFilter'            => ['sometimes', 'array'],
            'effects.imageFilter.enabled'    => ['sometimes', 'boolean'],
            'effects.imageFilter.preset'     => ['sometimes', 'in:none,grayscale,sepia,muted,high-contrast,custom'],
            'effects.imageFilter.grayscale'  => ['sometimes', 'integer', 'min:0', 'max:100'],
            'effects.imageFilter.sepia'      => ['sometimes', 'integer', 'min:0', 'max:100'],
            'effects.imageFilter.brightness' => ['sometimes', 'integer', 'min:50', 'max:200'],
            'effects.imageFilter.contrast'   => ['sometimes', 'integer', 'min:50', 'max:200'],
            'effects.imageFilter.saturation' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'effects.overlay'                => ['sometimes', 'array'],
            'effects.overlay.enabled'        => ['sometimes', 'boolean'],
            'effects.overlay.color'          => ['sometimes', 'string', 'max:20'],
            'effects.overlay.opacity'        => ['sometimes', 'integer', 'min:0', 'max:100'],
            'effects.overlay.blendMode'      => ['sometimes', 'in:normal,multiply,screen,overlay,soft-light'],
            'effects.imageHoverReveal'            => ['sometimes', 'array'],
            'effects.imageHoverReveal.enabled'    => ['sometimes', 'boolean'],
            'effects.imageHoverReveal.mode'       => ['sometimes', 'in:none,fade,reveal-left,reveal-right,reveal-top,reveal-bottom,circle,diagonal'],
            'effects.imageHoverReveal.duration'   => ['sometimes', 'integer', 'min:150', 'max:1500'],
            'effects.imageHoverReveal.easing'     => ['sometimes', 'in:ease,ease-out,ease-in-out'],
        ];
    }

    // ═══════════════════════════════════════
    // Internal
    // ═══════════════════════════════════════

    private static function safeColor(string $color): string
    {
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) return $color;
        if (preg_match('/^rgba?\([\d\s.,]+\)$/', $color)) return $color;
        return '#000000';
    }
}
