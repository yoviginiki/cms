<?php

namespace App\Services\SiteWizard;

use App\Services\ThemeWizard\TokenProfileValidator;

/**
 * Deterministic computed-styles → TokenProfile mapper (NO AI). Takes the raw
 * style signals the extractor read off the entry page (fonts, colors, radii,
 * shadows, spacing) and produces a token profile that TokenProfileCompiler
 * can expand into a full theme document.
 *
 * The output is guaranteed to pass TokenProfileValidator: a bounded repair
 * loop re-runs validation and nudges colors (lightness/hue) until the
 * readability + distinctness rules hold, with a safe neutral fallback if the
 * source palette is hostile (e.g. white-on-white exports).
 */
class StyleProfileMapper
{
    public function __construct(private TokenProfileValidator $validator)
    {
    }

    public function map(array $signals, string $siteName): array
    {
        $background = $this->cssColor($signals['body']['background'] ?? null) ?? '#ffffff';
        $text = $this->cssColor($signals['body']['color'] ?? null) ?? '#333333';
        $heading = $this->cssColor($signals['h1']['color'] ?? null) ?? $text;

        $brand = $this->pickBrand($signals, $background);
        $accent = $this->pickAccent($signals, $brand, $background);

        $surfaceCandidate = $this->nearestHistogramColor($signals, $background);
        $surface = $surfaceCandidate ?? $this->blend($background, $text, 0.04);
        $muted = $this->blend($text, $background, 0.4);
        $border = $this->blend($text, $background, 0.8);

        $profile = [
            'name' => mb_substr($siteName ?: 'Imported site', 0, 40),
            'design_read' => $this->designRead($signals, $background, $brand),
            'palette' => [
                'brand' => $brand,
                'accent' => $accent,
                'background' => $background,
                'surface' => $surface,
                'text' => $text,
                'heading' => $heading,
                'muted' => $muted,
                'border' => $border,
            ],
            'typography' => [
                'display_character' => $this->fontCharacter($signals['h1']['fontFamily'] ?? '', true),
                'body_character' => $this->fontCharacter($signals['body']['fontFamily'] ?? '', false),
                'display_family' => $this->verbatimFamily($signals['h1']['fontFamily'] ?? ''),
                'body_family' => $this->verbatimFamily($signals['body']['fontFamily'] ?? ''),
                'scale' => $this->scale($signals),
                'heading_weight' => $this->headingWeight($signals),
            ],
            'spacing' => $this->spacing($signals),
            'radius' => $this->radius($signals),
            'shadow' => $this->shadow($signals),
            'layout' => 'standard',
        ];

        return $this->repair($profile);
    }

    /** Nudge colors until TokenProfileValidator passes; neutral fallback if it never does. */
    private function repair(array $profile): array
    {
        for ($i = 0; $i < 6; $i++) {
            $errors = $this->validator->validate($profile);
            if ($errors === []) {
                return $profile;
            }
            $pal = &$profile['palette'];
            foreach ($errors as $error) {
                if (str_contains($error, 'palette.text')) {
                    $pal['text'] = $this->forceContrast($pal['text'], $pal['background']);
                } elseif (str_contains($error, 'palette.heading')) {
                    $pal['heading'] = $this->forceContrast($pal['heading'], $pal['background']);
                } elseif (str_contains($error, 'palette.brand')) {
                    $pal['brand'] = $this->forceContrast($pal['brand'], $pal['background'], 2.0);
                } elseif (str_contains($error, 'palette.accent')) {
                    // Push the accent away from brand: rotate lightness, then hue.
                    $pal['accent'] = $i < 3
                        ? $this->shiftLightness($pal['accent'], $this->isLight($pal['accent']) ? -0.25 : 0.25)
                        : $this->rotateHue($pal['brand'], 40);
                } elseif (str_contains($error, 'palette.surface')) {
                    $pal['surface'] = $this->blend($pal['background'], $pal['text'], 0.04);
                }
            }
            unset($pal);
        }

        if ($this->validator->validate($profile) !== []) {
            $profile['palette'] = [
                'brand' => '#2563eb', 'accent' => '#7c3aed', 'background' => '#ffffff',
                'surface' => '#f8fafc', 'text' => '#334155', 'heading' => '#0f172a',
                'muted' => '#64748b', 'border' => '#e2e8f0',
            ];
        }

        return $profile;
    }

    // ── palette selection ──

    /** Most saturated visible color: button backgrounds → link color → theme-color meta → bg histogram. */
    private function pickBrand(array $signals, string $background): string
    {
        $candidates = [];
        foreach ($signals['buttons'] ?? [] as $button) {
            $hex = $this->cssColor($button['background'] ?? null);
            if ($hex !== null) {
                $candidates[] = $hex;
            }
        }
        if ($link = $this->cssColor($signals['link_color'] ?? null)) {
            $candidates[] = $link;
        }
        if ($meta = $this->cssColor($signals['theme_color_meta'] ?? null)) {
            $candidates[] = $meta;
        }
        foreach ($signals['background_histogram'] ?? [] as $entry) {
            if ($hex = $this->cssColor($entry['color'] ?? null)) {
                $candidates[] = $hex;
            }
        }

        $best = null;
        $bestScore = -1.0;
        foreach ($candidates as $hex) {
            // A brand color is saturated and visible on the canvas.
            $score = $this->saturation($hex);
            if ($this->distance($hex, $background) < 30) {
                $score -= 0.5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $hex;
            }
        }

        return ($best !== null && $bestScore > 0.08) ? $best : '#2563eb';
    }

    /** Next saturated color clearly distinct from brand; fallback: hue-rotate brand. */
    private function pickAccent(array $signals, string $brand, string $background): string
    {
        $candidates = [];
        foreach ($signals['buttons'] ?? [] as $button) {
            if ($hex = $this->cssColor($button['background'] ?? null)) {
                $candidates[] = $hex;
            }
        }
        if ($link = $this->cssColor($signals['link_color'] ?? null)) {
            $candidates[] = $link;
        }
        foreach ($signals['background_histogram'] ?? [] as $entry) {
            if ($hex = $this->cssColor($entry['color'] ?? null)) {
                $candidates[] = $hex;
            }
        }

        foreach ($candidates as $hex) {
            if ($this->saturation($hex) > 0.15 && $this->distance($hex, $brand) > 60 && $this->distance($hex, $background) > 40) {
                return $hex;
            }
        }

        return $this->rotateHue($brand, 40);
    }

    private function nearestHistogramColor(array $signals, string $background): ?string
    {
        foreach (array_slice($signals['background_histogram'] ?? [], 0, 5) as $entry) {
            $hex = $this->cssColor($entry['color'] ?? null);
            if ($hex !== null && $hex !== $background && $this->distance($hex, $background) < 40) {
                return $hex;
            }
        }

        return null;
    }

    // ── typography / enums ──

    /**
     * The origin's first concrete family name, for verbatim use when it turns
     * out to be freely available (TokenProfileCompiler checks Google Fonts).
     * Generic keywords and OS stacks return '' — nothing to use verbatim.
     */
    private function verbatimFamily(string $family): string
    {
        $first = trim(explode(',', $family)[0] ?? '', " \t\"'");
        if ($first === '' || mb_strlen($first) > 40 || !preg_match('/^[a-z0-9][a-z0-9 \-]*$/i', $first)) {
            return '';
        }
        $generic = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui',
            'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', '-apple-system',
            'blinkmacsystemfont', 'segoe ui', 'arial', 'helvetica', 'helvetica neue',
            'times', 'times new roman', 'georgia', 'verdana', 'tahoma', 'trebuchet ms',
            'courier', 'courier new', 'iowan old style', 'palatino', 'palatino linotype', 'baskerville'];
        if (in_array(strtolower($first), $generic, true)) {
            return '';
        }

        return ucwords(strtolower($first));
    }

    /** Character string for FontAllowlist::suggest — derived from the REAL font-family. */
    private function fontCharacter(string $family, bool $display): string
    {
        $f = strtolower($family);
        $first = trim(explode(',', $f)[0] ?? '', " \t\"'");

        if (str_contains($f, 'mono') || str_contains($f, 'courier') || str_contains($f, 'consolas')) {
            return 'technical monospace';
        }
        $serifNames = ['georgia', 'times', 'garamond', 'playfair', 'merriweather', 'lora', 'baskerville', 'cambria', 'charter', 'spectral'];
        $isSerif = str_contains($f, 'serif') && !str_contains($f, 'sans-serif');
        foreach ($serifNames as $name) {
            $isSerif = $isSerif || str_contains($first, $name);
        }
        if ($isSerif) {
            return $display ? 'high-contrast elegant serif' : 'readable book serif';
        }
        if (str_contains($f, 'condensed') || str_contains($f, 'oswald') || str_contains($f, 'anton') || str_contains($f, 'bebas')) {
            return 'condensed bold grotesque';
        }
        $humanist = ['lato', 'open sans', 'source sans', 'nunito', 'karla', 'pt sans'];
        foreach ($humanist as $name) {
            if (str_contains($first, $name)) {
                return 'warm humanist sans';
            }
        }

        return $display ? 'clean modern sans' : 'neutral geometric sans';
    }

    private function headingWeight(array $signals): int
    {
        $weight = (int) ($signals['h1']['fontWeight'] ?? 700);

        return max(300, min(900, $weight ?: 700));
    }

    private function scale(array $signals): string
    {
        $h1 = (float) ($signals['h1']['fontSize'] ?? 0);
        $body = (float) ($signals['body']['fontSize'] ?? 16) ?: 16.0;
        $ratio = $h1 > 0 ? $h1 / $body : 2.2;

        return match (true) {
            $ratio >= 2.6 => 'dramatic',
            $ratio <= 1.8 => 'compact',
            default => 'balanced',
        };
    }

    private function spacing(array $signals): string
    {
        $padding = (float) ($signals['section_padding'] ?? 64);

        return match (true) {
            $padding < 48 => 'tight',
            $padding > 88 => 'airy',
            default => 'balanced',
        };
    }

    private function radius(array $signals): string
    {
        $radii = [];
        foreach ($signals['buttons'] ?? [] as $button) {
            $r = (float) ($button['radius'] ?? 0);
            if (!is_nan($r)) {
                $radii[] = $r;
            }
        }
        if ($radii === []) {
            return 'soft';
        }
        sort($radii);
        $median = $radii[intdiv(count($radii), 2)];

        return match (true) {
            $median < 2 => 'sharp',
            $median <= 10 => 'soft',
            default => 'rounded',
        };
    }

    private function shadow(array $signals): string
    {
        $ratio = (float) ($signals['shadow_ratio'] ?? 0);

        return match (true) {
            $ratio < 0.03 => 'none',
            $ratio < 0.15 => 'subtle',
            default => 'soft',
        };
    }

    private function designRead(array $signals, string $background, string $brand): string
    {
        $tone = $this->isLight($background) ? 'light' : 'dark';
        $face = str_contains($this->fontCharacter($signals['h1']['fontFamily'] ?? '', true), 'serif') ? 'serif' : 'sans-serif';
        $title = trim((string) ($signals['title'] ?? ''));

        return trim(($title !== '' ? "Imported from “{$title}”. " : 'Imported design. ')
            . "A {$tone} {$face} look built around {$brand}, read directly from the source styles.");
    }

    // ── color math ──

    /** Parse a computed CSS color (rgb/rgba/#hex) → #rrggbb, or null (incl. fully transparent). */
    public function cssColor(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^#([0-9a-f]{6})$/i', $value, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $m)) {
            $s = strtolower($m[1]);

            return "#{$s[0]}{$s[0]}{$s[1]}{$s[1]}{$s[2]}{$s[2]}";
        }
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/i', $value, $m)) {
            if (isset($m[4]) && (float) $m[4] < 0.5) {
                return null; // mostly transparent — not a real surface color
            }

            return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
        }

        return null;
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    private function blend(string $a, string $b, float $t): string
    {
        [$ar, $ag, $ab] = $this->rgb($a);
        [$br, $bg, $bb] = $this->rgb($b);

        return $this->hex(
            (int) round($ar + ($br - $ar) * $t),
            (int) round($ag + ($bg - $ag) * $t),
            (int) round($ab + ($bb - $ab) * $t),
        );
    }

    private function distance(string $a, string $b): float
    {
        [$ar, $ag, $ab] = $this->rgb($a);
        [$br, $bg, $bb] = $this->rgb($b);

        return sqrt(2 * ($ar - $br) ** 2 + 4 * ($ag - $bg) ** 2 + 3 * ($ab - $bb) ** 2) / 3;
    }

    private function saturation(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        return $max === 0 ? 0.0 : ($max - $min) / $max;
    }

    private function isLight(string $hex): bool
    {
        [$r, $g, $b] = $this->rgb($hex);

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.55;
    }

    private function shiftLightness(string $hex, float $amount): string
    {
        $target = $amount > 0 ? '#ffffff' : '#000000';

        return $this->blend($hex, $target, abs($amount));
    }

    /** Step toward readable: move the foreground away from the background until contrast holds. */
    private function forceContrast(string $fg, string $bg, float $target = 3.2): string
    {
        $direction = $this->isLight($bg) ? '#000000' : '#ffffff';
        $color = $fg;
        for ($i = 0; $i < 10; $i++) {
            if ($this->validator->contrast($color, $bg) >= $target) {
                return $color;
            }
            $color = $this->blend($color, $direction, 0.25);
        }

        return $direction === '#000000' ? '#1a1a1a' : '#f5f5f5';
    }

    /** Cheap hue rotation via RGB channel rotation blend — deterministic and dependency-free. */
    private function rotateHue(string $hex, int $degrees): string
    {
        [$r, $g, $b] = $this->rgb($hex);
        $t = ($degrees % 360) / 120;

        return $this->hex(
            (int) round($r + ($g - $r) * $t),
            (int) round($g + ($b - $g) * $t),
            (int) round($b + ($r - $b) * $t),
        );
    }
}
