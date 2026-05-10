<?php

namespace App\Support\Blocks;

class BlockStyle
{
    // ── Sanitizers ──

    /** Validate a CSS dimension: only safe numeric values with known units. */
    public static function safeDim(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        $s = trim((string) $v);
        if ($s === '0' || $s === 'auto') return $s;
        return preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', $s) ? $s : '';
    }

    /** Validate a CSS color: hex, rgb/rgba, oklch, hsl/hsla, or named. */
    public static function safeColor(mixed $v): string
    {
        if (!$v) return '';
        $s = trim((string) $v);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $s)) return $s;
        if (preg_match('/^(rgb|rgba|oklch|hsl|hsla)\([\d\s,.\/%]+\)$/i', $s)) return $s;
        if (preg_match('/^[a-zA-Z]{3,20}$/', $s)) return $s; // named colors
        return '';
    }

    /** Sanitize a CSS value: remove dangerous characters. */
    public static function safeCssVal(mixed $v): string
    {
        if (!$v) return '';
        return preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    }

    /** Sanitize custom class tokens: only safe characters. */
    public static function safeClass(mixed $v): string
    {
        if (!$v) return '';
        return trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string) $v));
    }

    /** Sanitize HTML ID: only safe characters. */
    public static function safeId(mixed $v): string
    {
        if (!$v) return '';
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $v);
    }

    // ── Shadow allowlist ──

    private const SHADOW_MAP = [
        'sm' => '0 1px 2px rgba(0,0,0,0.04)',
        'md' => '0 4px 12px rgba(0,0,0,0.06)',
        'lg' => '0 12px 32px rgba(0,0,0,0.10)',
    ];

    public static function safeShadow(mixed $v): string
    {
        if (!$v || $v === 'none') return '';
        return self::SHADOW_MAP[(string) $v] ?? '';
    }

    // ── Animation allowlist ──

    private const ANIMATION_NAMES = [
        'fade' => 'block-fade',
        'slide-up' => 'block-slide-up',
        'slide-left' => 'block-slide-left',
        'slide-right' => 'block-slide-right',
        'zoom' => 'block-zoom',
    ];

    public static function safeAnimationName(mixed $v): string
    {
        if (!$v || $v === 'none') return '';
        return self::ANIMATION_NAMES[(string) $v] ?? '';
    }

    // ── Border style allowlist ──

    private const BORDER_STYLES = ['solid', 'dashed', 'dotted'];

    // ── Builders ──

    /**
     * Build inline style string from block shared properties.
     * All values are sanitized before output.
     */
    public static function buildStyle(array $blockStyle = [], array $blockAnimation = []): string
    {
        $parts = [];

        // Spacing
        $sp = $blockStyle['spacing'] ?? [];
        foreach (['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
                   'marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as $prop) {
            $v = self::safeDim($sp[$prop] ?? '');
            if ($v) {
                $kebab = strtolower(preg_replace('/[A-Z]/', '-$0', $prop));
                $parts[] = "{$kebab}:{$v}";
            }
        }

        // Visual
        $vis = $blockStyle['visual'] ?? [];

        // Border
        if (!empty($vis['borderWidth']) && !empty($vis['borderColor'])) {
            $bw = self::safeDim($vis['borderWidth']);
            $bc = self::safeColor($vis['borderColor']);
            $bs = in_array($vis['borderStyle'] ?? 'solid', self::BORDER_STYLES)
                ? ($vis['borderStyle'] ?? 'solid') : 'solid';
            if ($bw && $bc) $parts[] = "border:{$bw} {$bs} {$bc}";
        }

        // Border radius
        $br = self::safeDim($vis['borderRadius'] ?? '');
        if ($br) {
            $parts[] = "border-radius:{$br}";
            $parts[] = "overflow:hidden";
        }

        // Shadow
        $shadow = self::safeShadow($vis['boxShadow'] ?? '');
        if ($shadow) $parts[] = "box-shadow:{$shadow}";

        // Opacity
        if (isset($vis['opacity']) && (float) $vis['opacity'] < 1) {
            $op = max(0, min(1, (float) $vis['opacity']));
            $parts[] = "opacity:{$op}";
        }

        // Animation
        $entrance = $blockAnimation['entrance'] ?? 'none';
        $animName = self::safeAnimationName($entrance);
        if ($animName) {
            $dur = max(50, min(3000, (int) ($blockAnimation['duration'] ?? 400)));
            $del = max(0, min(5000, (int) ($blockAnimation['delay'] ?? 0)));
            $parts[] = "animation-name:{$animName}";
            $parts[] = "animation-duration:{$dur}ms";
            $parts[] = "animation-delay:{$del}ms";
            $parts[] = "animation-fill-mode:both";
        }

        return implode(';', $parts);
    }

    /**
     * Build class string from block shared properties.
     */
    public static function buildClasses(
        array $blockAdvanced = [],
        string ...$extra
    ): string {
        $classes = [];

        // Custom class (sanitized)
        $custom = self::safeClass($blockAdvanced['customClass'] ?? '');
        if ($custom) $classes[] = $custom;

        // Extra classes passed by the block template
        foreach ($extra as $c) {
            if ($c) $classes[] = $c;
        }

        return implode(' ', $classes);
    }

    /**
     * Build animation data attribute value.
     * Returns empty string if no animation.
     */
    public static function animationAttr(array $blockAnimation = []): string
    {
        $entrance = $blockAnimation['entrance'] ?? 'none';
        if ($entrance === 'none' || !isset(self::ANIMATION_NAMES[$entrance])) return '';
        return $entrance;
    }

    /**
     * Build responsive hideOn scoped CSS.
     * Returns ['scopeClass' => string, 'css' => string].
     */
    public static function buildHideOnCss(array $blockResponsive = [], string $htmlId = ''): array
    {
        $hideOn = $blockResponsive['hideOn'] ?? [];
        if (empty($hideOn)) return ['scopeClass' => '', 'css' => ''];

        $scope = 'blk-' . substr(md5($htmlId ?: uniqid('', true)), 0, 8);
        $css = '';

        if (in_array('desktop', $hideOn)) {
            $css .= "@media(min-width:1025px){.{$scope}{display:none!important}}";
        }
        if (in_array('tablet', $hideOn)) {
            $css .= "@media(min-width:769px) and (max-width:1024px){.{$scope}{display:none!important}}";
        }
        if (in_array('mobile', $hideOn)) {
            $css .= "@media(max-width:768px){.{$scope}{display:none!important}}";
        }

        return ['scopeClass' => $scope, 'css' => $css];
    }
}
