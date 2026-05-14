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

    // Unified shadow preset map — accepts both naming conventions
    private const SHADOW_MAP = [
        'sm' => '0 1px 2px rgba(0,0,0,0.04)',
        'md' => '0 4px 12px rgba(0,0,0,0.06)',
        'lg' => '0 12px 32px rgba(0,0,0,0.10)',
        'subtle' => '0 1px 3px rgba(0,0,0,0.12)',
        'medium' => '0 8px 24px rgba(0,0,0,0.18)',
        'large'  => '0 20px 40px rgba(0,0,0,0.24)',
        'glow'   => '0 0 30px rgba(255,255,255,0.35)',
    ];

    public static function safeShadow(mixed $v): string
    {
        if (!$v || $v === 'none') return '';
        return self::SHADOW_MAP[(string) $v] ?? '';
    }

    /**
     * Build safe box-shadow CSS from structured shadow data.
     * Supports preset mode (allowlisted map) and custom mode (validated fields).
     */
    public static function buildShadowCss(string $mode = 'preset', string $preset = '', ?array $custom = null): string
    {
        if ($mode === 'custom' && $custom) {
            $x = self::safeDim($custom['x'] ?? '0px') ?: '0px';
            $y = self::safeDim($custom['y'] ?? '4px') ?: '4px';
            // Blur must be non-negative (CSS spec)
            $rawBlur = self::safeDim($custom['blur'] ?? '12px') ?: '12px';
            $blur = str_starts_with($rawBlur, '-') ? '12px' : $rawBlur;
            $spread = self::safeDim($custom['spread'] ?? '0px') ?: '0px';
            $color = self::safeColor($custom['color'] ?? '#000000') ?: '#000000';
            $alpha = max(0, min(100, (int) ($custom['opacity'] ?? 15))) / 100;
            $inset = !empty($custom['inset']) ? 'inset ' : '';

            // Convert color + opacity to rgba
            if (str_starts_with($color, '#')) {
                $hex = ltrim($color, '#');
                if (strlen($hex) === 3) {
                    $r = hexdec($hex[0] . $hex[0]);
                    $g = hexdec($hex[1] . $hex[1]);
                    $b = hexdec($hex[2] . $hex[2]);
                } elseif (strlen($hex) >= 6) {
                    $r = hexdec(substr($hex, 0, 2));
                    $g = hexdec(substr($hex, 2, 2));
                    $b = hexdec(substr($hex, 4, 2));
                } else {
                    $r = $g = $b = 0;
                }
                $colorVal = "rgba({$r},{$g},{$b}," . number_format($alpha, 2) . ")";
            } elseif (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $color, $m)) {
                // Already rgb/rgba — extract RGB and apply custom opacity
                $colorVal = "rgba({$m[1]},{$m[2]},{$m[3]}," . number_format($alpha, 2) . ")";
            } else {
                // oklch, hsl, named — use as-is (opacity not applied to non-hex/rgb)
                $colorVal = $color;
            }

            return "{$inset}{$x} {$y} {$blur} {$spread} {$colorVal}";
        }

        return self::safeShadow($preset);
    }

    // ── Animation allowlist ──

    private const ANIMATION_NAMES = [
        'fade' => 'block-fade',
        'slide-up' => 'block-slide-up',
        'slide-down' => 'block-slide-down',
        'slide-left' => 'block-slide-left',
        'slide-right' => 'block-slide-right',
        'zoom' => 'block-zoom',
        'scale-in' => 'block-scale-in',
    ];

    private const VALID_EASINGS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out'];

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

        // Background
        if (!empty($vis['backgroundGradient'])) {
            $parts[] = "background:{$vis['backgroundGradient']}";
        } elseif (!empty($vis['backgroundColor'])) {
            $bc = self::safeColor($vis['backgroundColor']);
            if ($bc) $parts[] = "background-color:{$bc}";
        }
        if (!empty($vis['backgroundImage'])) {
            $parts[] = "background-image:url(" . self::safeCssVal($vis['backgroundImage']) . ")";
            $parts[] = "background-size:cover";
            $parts[] = "background-position:center";
        }

        // Border
        if (!empty($vis['borderWidth']) && !empty($vis['borderColor'])) {
            $bw = self::safeDim($vis['borderWidth']);
            $bc = self::safeColor($vis['borderColor']);
            $bs = in_array($vis['borderStyle'] ?? 'solid', self::BORDER_STYLES)
                ? ($vis['borderStyle'] ?? 'solid') : 'solid';
            if ($bw && $bc) $parts[] = "border:{$bw} {$bs} {$bc}";
        }

        // Border radius — string (legacy) or per-corner object
        $brVal = $vis['borderRadius'] ?? '';
        if (is_array($brVal)) {
            $tl = self::safeDim($brVal['topLeft'] ?? '');
            $tr = self::safeDim($brVal['topRight'] ?? '');
            $br_ = self::safeDim($brVal['bottomRight'] ?? '');
            $bl = self::safeDim($brVal['bottomLeft'] ?? '');
            if ($tl || $tr || $br_ || $bl) {
                $radius = ($tl ?: '0') . ' ' . ($tr ?: '0') . ' ' . ($br_ ?: '0') . ' ' . ($bl ?: '0');
                $parts[] = "border-radius:{$radius}";
                $parts[] = "overflow:hidden";
            }
        } else {
            $br = self::safeDim($brVal);
            if ($br) {
                $parts[] = "border-radius:{$br}";
                $parts[] = "overflow:hidden";
            }
        }

        // Shadow — preset or custom
        $shadowMode = $vis['shadowMode'] ?? 'preset';
        if ($shadowMode === 'custom' && is_array($vis['shadowCustom'] ?? null)) {
            $shadowCss = self::buildShadowCss('custom', '', $vis['shadowCustom']);
            if ($shadowCss) $parts[] = "box-shadow:{$shadowCss}";
        } else {
            $shadow = self::safeShadow($vis['boxShadow'] ?? '');
            if ($shadow) $parts[] = "box-shadow:{$shadow}";
        }

        // Overflow (without border-radius)
        if (!empty($vis['overflow']) && in_array($vis['overflow'], ['hidden', 'scroll'])) {
            $parts[] = "overflow:{$vis['overflow']}";
        }

        // Layout
        $lay = $blockStyle['layout'] ?? [];
        $w = self::safeDim($lay['width'] ?? '');
        if ($w) $parts[] = "width:{$w}";
        $mw = self::safeDim($lay['maxWidth'] ?? '');
        if ($mw) $parts[] = "max-width:{$mw}";
        $mh = self::safeDim($lay['minHeight'] ?? '');
        if ($mh) $parts[] = "min-height:{$mh}";
        $z = $lay['zIndex'] ?? null;
        if ($z !== null && $z !== '' && is_numeric($z)) {
            $parts[] = "z-index:" . max(-100, min(9999, intval($z)));
        }
        // Alignment margins only apply when no explicit spacing margins are set
        $hasMarginL = !empty($sp['marginLeft'] ?? '');
        $hasMarginR = !empty($sp['marginRight'] ?? '');
        $align = $lay['alignment'] ?? '';
        if ($align === 'center' && !$hasMarginL && !$hasMarginR) { $parts[] = "margin-left:auto"; $parts[] = "margin-right:auto"; }
        elseif ($align === 'right' && !$hasMarginL) { $parts[] = "margin-left:auto"; $parts[] = "margin-right:0"; }
        elseif ($align === 'left' && !$hasMarginR) { $parts[] = "margin-left:0"; $parts[] = "margin-right:auto"; }
        $disp = $lay['display'] ?? 'block';
        if (in_array($disp, ['flex', 'grid', 'none'])) {
            $parts[] = "display:{$disp}";
            if ($disp === 'flex') {
                $fd = in_array($lay['flexDirection'] ?? '', ['row', 'column']) ? $lay['flexDirection'] : '';
                if ($fd) $parts[] = "flex-direction:{$fd}";
                $jc = in_array($lay['justifyContent'] ?? '', ['flex-start', 'center', 'flex-end', 'space-between']) ? $lay['justifyContent'] : '';
                if ($jc) $parts[] = "justify-content:{$jc}";
            }
        }

        // Animation
        $entrance = $blockAnimation['entrance'] ?? 'none';
        $animName = self::safeAnimationName($entrance);
        if ($animName) {
            $dur = max(50, min(3000, (int) ($blockAnimation['duration'] ?? 600)));
            $del = max(0, min(5000, (int) ($blockAnimation['delay'] ?? 0)));
            $easing = in_array($blockAnimation['easing'] ?? '', self::VALID_EASINGS) ? $blockAnimation['easing'] : 'ease-out';
            $parts[] = "animation-name:{$animName}";
            $parts[] = "animation-duration:{$dur}ms";
            $parts[] = "animation-delay:{$del}ms";
            $parts[] = "animation-timing-function:{$easing}";
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
