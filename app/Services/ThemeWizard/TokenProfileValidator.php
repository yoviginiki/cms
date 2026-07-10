<?php

namespace App\Services\ThemeWizard;

/**
 * Semantic validation of a token profile — the rules the JSON schema can't
 * express. Returns a flat list of human-readable errors ([] = valid), which
 * the SchemaRepairLoop feeds back to the model to self-correct.
 *
 * The goal is a theme that is READABLE and DISTINCT, not a pixel copy: body
 * text must contrast with the background, brand/accent must be visible and
 * differ from each other, and the enum choices must be in range.
 */
class TokenProfileValidator
{
    /** @return array<int,string> */
    public function validate(mixed $p): array
    {
        $e = [];
        if (!is_array($p)) return ['Profile must be an object.'];

        if (empty($p['name']) || !is_string($p['name'])) $e[] = 'name is required.';
        if (empty($p['design_read'])) $e[] = 'design_read is required.';

        $pal = $p['palette'] ?? null;
        $roles = ['brand', 'accent', 'background', 'surface', 'text', 'heading', 'muted', 'border'];
        if (!is_array($pal)) {
            $e[] = 'palette is required.';
        } else {
            foreach ($roles as $r) {
                if (!isset($pal[$r]) || !$this->isHex($pal[$r])) {
                    $e[] = "palette.{$r} must be a #RRGGBB color.";
                }
            }
            if ($e === [] || $this->allHex($pal, $roles)) {
                // readability: body + heading must contrast with the canvas
                if ($this->contrast($pal['text'], $pal['background']) < 3.0) {
                    $e[] = 'palette.text is too low-contrast against palette.background (needs a clearly readable body color).';
                }
                if ($this->contrast($pal['heading'], $pal['background']) < 3.0) {
                    $e[] = 'palette.heading is too low-contrast against palette.background.';
                }
                // distinctness: brand visible on canvas, accent ≠ brand
                if ($this->contrast($pal['brand'], $pal['background']) < 1.6) {
                    $e[] = 'palette.brand barely differs from the background — pick a visible brand color.';
                }
                if ($this->deltaE($pal['accent'], $pal['brand']) < 12) {
                    $e[] = 'palette.accent is nearly identical to palette.brand — make it a distinct secondary.';
                }
                // surface should sit close to the canvas (it is a subtle raise)
                if ($this->contrast($pal['surface'], $pal['background']) > 2.2) {
                    $e[] = 'palette.surface should be close to palette.background (a subtle raised tone, not a strong block).';
                }
            }
        }

        $typ = $p['typography'] ?? null;
        if (!is_array($typ)) {
            $e[] = 'typography is required.';
        } else {
            if (empty($typ['display_character'])) $e[] = 'typography.display_character is required.';
            if (empty($typ['body_character'])) $e[] = 'typography.body_character is required.';
            if (!in_array($typ['scale'] ?? null, TokenProfileSchema::SCALES, true)) $e[] = 'typography.scale must be compact|balanced|dramatic.';
            $w = $typ['heading_weight'] ?? null;
            if (!is_int($w) || $w < 300 || $w > 900) $e[] = 'typography.heading_weight must be 300–900.';
        }

        if (!in_array($p['spacing'] ?? null, TokenProfileSchema::DENSITIES, true)) $e[] = 'spacing must be tight|balanced|airy.';
        if (!in_array($p['radius'] ?? null, TokenProfileSchema::RADII, true)) $e[] = 'radius must be sharp|soft|rounded.';
        if (!in_array($p['shadow'] ?? null, TokenProfileSchema::SHADOWS, true)) $e[] = 'shadow must be none|subtle|soft.';
        if (!in_array($p['layout'] ?? null, TokenProfileSchema::LAYOUTS, true)) $e[] = 'layout must be one of ' . implode(', ', TokenProfileSchema::LAYOUTS) . '.';

        return $e;
    }

    private function isHex(mixed $v): bool
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1;
    }

    private function allHex(array $pal, array $roles): bool
    {
        foreach ($roles as $r) if (!$this->isHex($pal[$r] ?? null)) return false;
        return true;
    }

    /** @return array{0:float,1:float,2:float} linear RGB 0..1 */
    private function rgb(string $hex): array
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        return [$r / 255, $g / 255, $b / 255];
    }

    private function relLuminance(string $hex): float
    {
        $lin = array_map(function ($c) {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $this->rgb($hex));
        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    /** WCAG contrast ratio (1..21). */
    public function contrast(string $a, string $b): float
    {
        $la = $this->relLuminance($a);
        $lb = $this->relLuminance($b);
        return ($la > $lb ? ($la + 0.05) / ($lb + 0.05) : ($lb + 0.05) / ($la + 0.05));
    }

    /** Cheap perceptual distance (weighted RGB) — enough to reject near-duplicates. */
    private function deltaE(string $a, string $b): float
    {
        [$ar, $ag, $ab] = $this->rgb($a);
        [$br, $bg, $bb] = $this->rgb($b);
        $dr = ($ar - $br) * 255; $dg = ($ag - $bg) * 255; $db = ($ab - $bb) * 255;
        return sqrt(2 * $dr * $dr + 4 * $dg * $dg + 3 * $db * $db) / 3;
    }
}
