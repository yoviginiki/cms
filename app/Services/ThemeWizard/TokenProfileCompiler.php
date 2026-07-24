<?php

namespace App\Services\ThemeWizard;

use Illuminate\Support\Str;

/**
 * Expands a validated token profile (the wizard's "design read") into a full
 * T1 theme.json document — the same W3C semantic shape the first-party themes
 * use, so it resolves through DesignTokenGenerator and previews through the
 * layout-aware studioFrame with no special-casing.
 *
 * The profile is high-level (a feel); this class makes the honest concrete
 * choices: substitutes open fonts from the allowlist by character, maps the
 * scale/spacing/radius/shadow words to token values, and derives the neutrals
 * the profile doesn't state (inverse, border tiers, footer) from the palette.
 */
class TokenProfileCompiler
{
    private const SCALES = [
        'compact'  => ['xs' => '0.75rem', 'sm' => '0.875rem', 'base' => '1rem', 'lg' => '1.15rem', 'xl' => '1.35rem', '2xl' => '1.7rem', '3xl' => '2.1rem', '4xl' => '2.6rem', '5xl' => '3.2rem'],
        'balanced' => ['xs' => '0.75rem', 'sm' => '0.9rem', 'base' => '1.0625rem', 'lg' => '1.3rem', 'xl' => '1.6rem', '2xl' => '2rem', '3xl' => '2.6rem', '4xl' => '3.4rem', '5xl' => '4.2rem'],
        'dramatic' => ['xs' => '0.72rem', 'sm' => '0.86rem', 'base' => '1.0625rem', 'lg' => '1.35rem', 'xl' => '1.7rem', '2xl' => '2.3rem', '3xl' => '3.2rem', '4xl' => '4.4rem', '5xl' => '6rem'],
    ];
    private const DENSITY = [
        'tight'    => ['section' => 'clamp(40px,5vw,72px)', 'gap' => 'clamp(16px,2vw,24px)'],
        'balanced' => ['section' => 'clamp(56px,7vw,104px)', 'gap' => 'clamp(20px,2.4vw,32px)'],
        'airy'     => ['section' => 'clamp(72px,9vw,144px)', 'gap' => 'clamp(28px,3.2vw,48px)'],
    ];
    private const RADIUS = [
        'sharp'   => ['none' => '0', 'sm' => '0', 'md' => '0', 'lg' => '0', 'xl' => '0', 'full' => '0'],
        'soft'    => ['none' => '0', 'sm' => '4px', 'md' => '8px', 'lg' => '14px', 'xl' => '20px', 'full' => '999px'],
        'rounded' => ['none' => '0', 'sm' => '8px', 'md' => '14px', 'lg' => '20px', 'xl' => '28px', 'full' => '999px'],
    ];
    private const SHADOW = [
        'none'   => ['sm' => 'none', 'md' => 'none', 'lg' => 'none', 'xl' => 'none'],
        'subtle' => ['sm' => '0 1px 2px rgba(16,24,40,0.06)', 'md' => '0 4px 12px rgba(16,24,40,0.08)', 'lg' => '0 12px 32px rgba(16,24,40,0.10)', 'xl' => '0 24px 48px rgba(16,24,40,0.12)'],
        'soft'   => ['sm' => '0 4px 16px rgba(40,30,20,0.08)', 'md' => '0 8px 30px rgba(40,30,20,0.10)', 'lg' => '0 16px 44px rgba(40,30,20,0.12)', 'xl' => '0 28px 60px rgba(40,30,20,0.14)'],
    ];

    /**
     * @param array $p a profile already passed by TokenProfileValidator
     * @return array{name:string, slug:string, description:string, document:array}
     */
    public function compile(array $p): array
    {
        $pal = $p['palette'];
        $typ = $p['typography'];
        $col = fn ($v) => ['$type' => 'color', '$value' => $v];
        $dim = fn ($v) => ['$type' => 'dimension', '$value' => $v];
        $num = fn ($v) => ['$type' => 'number', '$value' => (string) $v];
        $fam = fn (array $v) => ['$type' => 'fontFamily', '$value' => $v];

        $display = $this->resolveFont('display', (string) ($typ['display_family'] ?? ''), $typ['display_character']);
        $body = $this->resolveFont('body', (string) ($typ['body_family'] ?? ''), $typ['body_character']);
        $displayStack = [$display, ...$this->fallbackFor($display, $typ['display_character'])];
        $bodyStack = [$body, ...$this->fallbackFor($body, $typ['body_character'])];

        $scaleKey = $typ['scale'] ?? 'balanced';
        $scale = self::SCALES[$scaleKey] ?? self::SCALES['balanced'];
        $density = self::DENSITY[$p['spacing']] ?? self::DENSITY['balanced'];
        $radius = self::RADIUS[$p['radius']] ?? self::RADIUS['sharp'];
        $shadow = self::SHADOW[$p['shadow']] ?? self::SHADOW['none'];

        // derive neutrals the profile doesn't state. The inverse surface is a
        // dark ground for footers/cinematic heroes: for a LIGHT theme, darken
        // the heading; for an already-DARK theme, the canvas itself is the dark
        // ground (darkening the light heading would only give grey).
        $inverse = $this->luminance($pal['background']) < 0.25
            ? $this->darker($pal['background'])
            : $this->darker($pal['heading']);
        $btnRadius = $p['radius'] === 'rounded' ? '999px' : ($radius['md'] ?? '0');
        $btnTransform = in_array($p['layout'], ['cinematic', 'portfolio'], true) ? 'uppercase' : 'none';
        $btnTracking = $btnTransform === 'uppercase' ? '0.12em' : '0.01em';
        $onBrand = $this->contrastText($pal['brand']);

        $document = [
            '$metadata' => ['name' => $p['name'], 'version' => '1.0.0', 'modes' => ['light'], 'author' => 'Theme Wizard'],
            'layout' => ['style' => $p['layout']],
            'wizard' => ['design_read' => $p['design_read'], 'display_font' => $display, 'body_font' => $body],
            'semantic' => [
                'color' => [
                    'brand' => $col($pal['brand']), 'accent' => $col($pal['accent']),
                    'success' => $col('#3E7A54'), 'warning' => $col('#C98A1E'), 'danger' => $col($pal['accent']),
                    'background' => [
                        'canvas' => $col($pal['background']), 'surface' => $col($pal['surface']),
                        'raised' => $col('#FFFFFF'), 'overlay' => $col('rgba(0,0,0,0.5)'), 'inverse' => $col($inverse),
                    ],
                    'text' => [
                        'body' => $col($pal['text']), 'heading' => $col($pal['heading']),
                        'muted' => $col($pal['muted']), 'link' => $col($pal['brand']),
                        'inverse' => $col($pal['background']),
                    ],
                    'border' => [
                        'subtle' => $col($pal['surface']), 'default' => $col($pal['border']), 'strong' => $col($pal['muted']),
                    ],
                ],
                'font' => [
                    'family' => [
                        'display' => $fam($displayStack), 'body' => $fam($bodyStack),
                        'mono' => $fam(['JetBrains Mono', 'ui-monospace', 'monospace']),
                        'nav' => $fam($displayStack), 'button' => $fam($displayStack),
                    ],
                    'size' => array_map($dim, $scale),
                    'lineHeight' => ['body' => $num('1.65'), 'heading' => $num($scaleKey === 'dramatic' ? '1.0' : '1.15')],
                    'letterSpacing' => ['body' => $dim('0'), 'heading' => $dim('-0.01em')],
                    'weight' => ['heading' => $num($typ['heading_weight'])],
                ],
                'size' => [
                    'space' => ['section' => $dim($density['section']), 'container' => $dim('1280px'), 'gap' => $dim($density['gap'])],
                    'radius' => array_map($dim, $radius),
                ],
                'shadow' => array_map(fn ($v) => ['$type' => 'shadow', '$value' => $v], $shadow),
                'btn' => [
                    'bg' => $col($pal['brand']), 'color' => $col($onBrand), 'border' => $col('transparent'),
                    'hoverBg' => $col($pal['accent']), 'hoverColor' => $col($onBrand),
                    'padding' => ['$type' => 'string', '$value' => '13px 26px'],
                    'fontWeight' => $num(600), 'tracking' => $dim($btnTracking),
                    'transform' => ['$type' => 'string', '$value' => $btnTransform],
                    'radius' => $dim($btnRadius),
                ],
                'footer' => [
                    'bg' => $col($inverse), 'color' => $col($pal['muted']), 'borderColor' => $col($pal['border']),
                ],
                'content' => ['maxWidth' => $dim('760px'), 'proseMaxWidth' => $dim('66ch')],
            ],
        ];

        return [
            'name' => $p['name'],
            'slug' => Str::slug($p['name']) . '-' . Str::lower(Str::random(4)),
            'description' => $p['design_read'],
            'document' => $document,
        ];
    }

    /**
     * The reference's EXACT family when it is freely available on Google Fonts
     * (the fidelity gap that made imported sites read "off": Spectral became
     * Playfair, Manrope became Inter). Unknown, licensed, or unverifiable
     * families fall back to the character-matched open substitute — the
     * allowlist stays the licensing guardrail, not a straitjacket.
     */
    private function resolveFont(string $role, string $family, string|array $character): string
    {
        if ($family !== '' && self::googleFontAvailable($family)) {
            return $family;
        }

        return FontAllowlist::suggest($role, $character);
    }

    /** Cached css2 probe — Google returns 400 for families it does not host. */
    public static function googleFontAvailable(string $family): bool
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{0,50}$/', $family)) {
            return false;
        }

        return (bool) \Illuminate\Support\Facades\Cache::remember(
            'gfont:' . strtolower($family),
            now()->addDays(7),
            function () use ($family) {
                try {
                    return \Illuminate\Support\Facades\Http::timeout(6)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                        ->get('https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $family))
                        ->successful();
                } catch (\Throwable) {
                    return false; // offline/flaky network → open substitute
                }
            }
        );
    }

    /** Generic CSS fallbacks after the chosen family, by its category. */
    private function fallbackFor(string $font, string|array $character): array
    {
        $known = FontAllowlist::get($font);
        $char = $known['character'] ?? [];
        $cat = $known['category'] ?? 'body';
        $phrase = is_array($character) ? implode(' ', $character) : $character;
        $isSerif = in_array('serif', $char, true)
            || ($known === null && str_contains(strtolower($phrase), 'serif') && !str_contains(strtolower($phrase), 'sans'));
        if ($isSerif) return ['Georgia', 'serif'];
        if ($cat === 'mono') return ['ui-monospace', 'monospace'];
        return ['system-ui', '-apple-system', 'sans-serif'];
    }

    /** Perceptual luminance 0..1 of a #RRGGBB color. */
    private function luminance(string $hex): float
    {
        $r = hexdec(substr($hex, 1, 2)); $g = hexdec(substr($hex, 3, 2)); $b = hexdec(substr($hex, 5, 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    }

    /** Nudge a color darker for inverse surfaces (multiply by 0.4, floor). */
    private function darker(string $hex): string
    {
        $r = max(10, (int) (hexdec(substr($hex, 1, 2)) * 0.35));
        $g = max(10, (int) (hexdec(substr($hex, 3, 2)) * 0.35));
        $b = max(10, (int) (hexdec(substr($hex, 5, 2)) * 0.35));
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /** Black or white text for best contrast on a given background. */
    private function contrastText(string $hex): string
    {
        $r = hexdec(substr($hex, 1, 2)); $g = hexdec(substr($hex, 3, 2)); $b = hexdec(substr($hex, 5, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $lum > 0.6 ? '#111111' : '#FFFFFF';
    }
}
