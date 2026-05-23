<?php

namespace App\Domain\Theme\Services;

use App\Models\Site;
use App\Models\Theme;

class DesignTokenGenerator
{
    /**
     * Generate CSS custom properties from theme config + customizations.
     */
    /**
     * Map from W3C token paths to legacy CSS variable names.
     * Every token is also emitted as --semantic-{path} for Studio/Blade consistency.
     */
    private const W3C_TO_CSS = [
        'semantic.color.brand' => 'color-primary',
        'semantic.color.accent' => 'color-accent',
        'semantic.color.success' => 'color-success',
        'semantic.color.warning' => 'color-warning',
        'semantic.color.danger' => 'color-danger',
        'semantic.color.background.canvas' => 'color-bg',
        'semantic.color.background.surface' => 'color-bg-alt',
        'semantic.color.background.raised' => 'color-bg-raised',
        'semantic.color.background.overlay' => 'color-bg-overlay',
        'semantic.color.background.inverse' => 'color-bg-inverse',
        'semantic.color.text.body' => 'color-text',
        'semantic.color.text.heading' => 'color-heading',
        'semantic.color.text.h1' => 'color-h1',
        'semantic.color.text.h2' => 'color-h2',
        'semantic.color.text.h3' => 'color-h3',
        'semantic.color.text.h4' => 'color-h4',
        'semantic.color.text.h5' => 'color-h5',
        'semantic.color.text.h6' => 'color-h6',
        'semantic.color.text.muted' => 'color-text-muted',
        'semantic.color.text.link' => 'color-link',
        'semantic.color.text.link.hover' => 'color-link-hover',
        'semantic.text.decoration.link' => 'text-decoration-link',
        'semantic.text.decoration.link.hover' => 'text-decoration-link-hover',
        'semantic.color.text.inverse' => 'color-text-inverse',
        'semantic.color.border.default' => 'color-border',
        'semantic.color.border.subtle' => 'color-border-light',
        'semantic.color.border.strong' => 'color-border-strong',
        'semantic.font.family.display' => 'font-heading',
        'semantic.font.family.body' => 'font-body',
        'semantic.font.family.mono' => 'font-mono',
        'semantic.font.family.h1' => 'font-h1',
        'semantic.font.family.h2' => 'font-h2',
        'semantic.font.family.h3' => 'font-h3',
        'semantic.font.family.h4' => 'font-h4',
        'semantic.font.family.h5' => 'font-h5',
        'semantic.font.family.h6' => 'font-h6',
        'semantic.font.family.button' => 'font-button',
        'semantic.font.family.nav' => 'font-nav',
        'semantic.font.size.xs' => 'font-size-xs',
        'semantic.font.size.sm' => 'font-size-sm',
        'semantic.font.size.base' => 'font-size-base',
        'semantic.font.size.lg' => 'font-size-lg',
        'semantic.font.size.xl' => 'font-size-xl',
        'semantic.font.size.2xl' => 'font-size-2xl',
        'semantic.font.size.3xl' => 'font-size-3xl',
        'semantic.font.size.4xl' => 'font-size-4xl',
        'semantic.font.size.5xl' => 'font-size-5xl',
        'semantic.size.radius.none' => 'border-radius-none',
        'semantic.size.radius.sm' => 'border-radius-sm',
        'semantic.size.radius.md' => 'border-radius-md',
        'semantic.size.radius.lg' => 'border-radius-lg',
        'semantic.size.radius.xl' => 'border-radius-xl',
        'semantic.size.radius.full' => 'border-radius-full',
        'semantic.shadow.sm' => 'shadow-sm',
        'semantic.shadow.md' => 'shadow-md',
        'semantic.shadow.lg' => 'shadow-lg',
        'semantic.shadow.xl' => 'shadow-xl',
    ];

    public function generate(Site $site): string
    {
        $theme = $site->theme;
        if (!$theme) return '';

        $defaults = $this->getDefaults();
        $themeTokens = $theme->config['tokens'] ?? [];
        $customizations = $this->getCustomizations($site, $theme);

        // Merge: defaults → config tokens → customizations
        $tokens = array_merge($defaults, $themeTokens, $customizations);

        // Bridge W3C document tokens → CSS variables (studio edits affect published site)
        if ($theme->document) {
            $docTokens = $this->resolveDocumentTokens($theme->document);
            $tokens = array_merge($tokens, $docTokens);
        }

        // Font imports must come before style rules per CSS spec
        $css = $this->generateFontImports($tokens);

        $css .= ":root {\n";
        foreach ($tokens as $key => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            // Sanitize: strip characters that could break out of CSS declarations
            $safeKey = preg_replace('/[^a-zA-Z0-9\-_]/', '', $key);
            $safeValue = preg_replace('/[{}<>;\\\\]/', '', (string) $value);
            $css .= "  --{$safeKey}: {$safeValue};\n";
        }
        $css .= "}\n";

        // Site Background from theme document
        if ($theme->document && isset($theme->document['siteBackground'])) {
            $bg = $theme->document['siteBackground'];
            $css .= "body {\n";
            if (!empty($bg['color'])) {
                $css .= "  background-color: " . preg_replace('/[^a-zA-Z0-9#(),.\s%]/', '', $bg['color']) . ";\n";
            }
            $css .= "  min-height: 100vh;\n  position: relative;\n";
            $css .= "}\n";

            // Background image via ::after (supports opacity without affecting content)
            if (!empty($bg['image'])) {
                $src = $bg['image'];
                // Only allow safe URL patterns — block protocol-relative //
                if (preg_match('#^(https?://|/[^/])#', $src)) {
                    $imgOpacity = max(0, min(1, (float) ($bg['imageOpacity'] ?? 1)));
                    $css .= "body::after {\n";
                    $css .= "  content: '';\n  position: fixed;\n  inset: 0;\n  z-index: -2;\n  pointer-events: none;\n";
                    $css .= "  background-image: url('" . addcslashes($src, "'\\") . "');\n";
                    $css .= "  background-size: " . preg_replace('/[^a-z]/', '', $bg['imageSize'] ?? 'cover') . ";\n";
                    $css .= "  background-position: " . preg_replace('/[^a-z\s]/', '', $bg['imagePosition'] ?? 'center') . ";\n";
                    $css .= "  background-repeat: " . preg_replace('/[^a-z\-]/', '', $bg['imageRepeat'] ?? 'no-repeat') . ";\n";
                    $css .= "  background-attachment: " . preg_replace('/[^a-z]/', '', $bg['imageAttachment'] ?? 'scroll') . ";\n";
                    $css .= "  opacity: {$imgOpacity};\n";
                    $css .= "}\n";
                }
            }

            // Gradient overlay via ::before pseudo-element
            if (!empty($bg['gradientEnabled'])) {
                $from = preg_replace('/[^a-zA-Z0-9#(),.\s%]/', '', $bg['gradientFrom'] ?? '#000000');
                $to = preg_replace('/[^a-zA-Z0-9#(),.\s%]/', '', $bg['gradientTo'] ?? '#ffffff');
                $dir = preg_replace('/[^a-z\s]/', '', $bg['gradientDirection'] ?? 'to bottom');
                $opacity = max(0, min(1, (float) ($bg['gradientOpacity'] ?? 0.5)));
                $css .= "body::before {\n";
                $css .= "  content: '';\n  position: fixed;\n  inset: 0;\n  z-index: -1;\n  pointer-events: none;\n";
                $css .= "  background: linear-gradient({$dir}, {$from}, {$to});\n";
                $css .= "  opacity: {$opacity};\n";
                $css .= "}\n";
            }
        }

        return $css;
    }

    /**
     * Resolve W3C Design Token document to flat CSS variable names.
     */
    private function resolveDocumentTokens(array $document): array
    {
        try {
            $merger = new \App\Services\Theme\TokenMerger();
            $refs = new \App\Services\Theme\ReferenceResolver();
            $flat = $refs->flatten($merger->merge([$document]));
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($flat as $path => $value) {
            if (is_array($value)) {
                $value = "'" . implode("', '", $value) . "'";
            }

            // Emit --semantic-* variable (matches Studio iframe CSS vars)
            $semanticName = str_replace('.', '-', $path);
            $result[$semanticName] = $value;

            // Also emit legacy --color-*/--font-* alias if mapped
            $cssName = self::W3C_TO_CSS[$path] ?? null;
            if ($cssName) {
                $result[$cssName] = $value;
            }
        }

        return $result;
    }

    /**
     * Get customizations from theme_customizations table.
     */
    private function getCustomizations(Site $site, Theme $theme): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('theme_customizations')
            ->where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->pluck('token_value', 'token_key')
            ->toArray();

        return $rows;
    }

    /**
     * Generate Google Fonts @import for all font tokens.
     * Collects unique font families from heading, body, mono, h1-h6, button, nav tokens.
     */
    private function generateFontImports(array $tokens): string
    {
        // Collect all font token keys and their values
        $fontKeys = ['font-heading', 'font-body', 'font-mono',
            'font-h1', 'font-h2', 'font-h3', 'font-h4', 'font-h5', 'font-h6',
            'font-button', 'font-nav'];

        $uniqueFonts = [];
        foreach ($fontKeys as $key) {
            $val = $tokens[$key] ?? null;
            if (!$val || str_contains($val, 'system-ui') || str_contains($val, 'ui-monospace')) continue;
            $clean = trim(explode(',', $val)[0], "' \"");
            if (!$clean || isset($uniqueFonts[$clean])) continue;
            $uniqueFonts[$clean] = true;
        }

        $fonts = [];
        foreach (array_keys($uniqueFonts) as $fontName) {
            $fonts[] = urlencode($fontName) . ':wght@300;400;500;600;700';
        }

        if (empty($fonts)) return '';

        $families = implode('&family=', $fonts);
        return "@import url('https://fonts.googleapis.com/css2?family={$families}&display=swap');\n";
    }

    /**
     * Get default design tokens.
     */
    public function getDefaults(): array
    {
        return [
            // Colors
            'color-primary' => '#3b82f6',
            'color-primary-dark' => '#2563eb',
            'color-primary-light' => '#93c5fd',
            'color-secondary' => '#64748b',
            'color-accent' => '#f59e0b',
            'color-text' => '#1e293b',
            'color-heading' => '#0f172a',
            'color-text-muted' => '#64748b',
            'color-text-inverse' => '#ffffff',
            'color-link' => '#3b82f6',
            'color-link-hover' => '#2563eb',
            'text-decoration-link' => 'none',
            'text-decoration-link-hover' => 'underline',
            'color-bg' => '#ffffff',
            'color-bg-alt' => '#f8fafc',
            'color-bg-raised' => '#ffffff',
            'color-bg-overlay' => 'rgba(0,0,0,0.5)',
            'color-bg-inverse' => '#0f172a',
            'color-border' => '#e2e8f0',
            'color-border-light' => '#f1f5f9',
            'color-border-strong' => '#94a3b8',
            'color-success' => '#22c55e',
            'color-warning' => '#f59e0b',
            'color-danger' => '#ef4444',
            'color-info' => '#3b82f6',

            // Typography
            'font-heading' => "'Inter', system-ui, -apple-system, sans-serif",
            'font-body' => "'Inter', system-ui, -apple-system, sans-serif",
            'font-mono' => "ui-monospace, 'SF Mono', monospace",
            'font-size-xs' => '0.75rem',
            'font-size-base' => 'clamp(14px, 1vw + 12px, 18px)',
            'font-size-sm' => '0.875rem',
            'font-size-lg' => '1.125rem',
            'font-size-xl' => '1.25rem',
            'font-size-2xl' => '1.5rem',
            'font-size-3xl' => '2rem',
            'font-size-4xl' => '2.25rem',
            'font-size-5xl' => '3rem',
            'font-weight-normal' => '400',
            'font-weight-medium' => '500',
            'font-weight-bold' => '700',
            'line-height-tight' => '1.25',
            'line-height-normal' => '1.6',
            'line-height-relaxed' => '1.8',
            'letter-spacing-tight' => '-0.025em',
            'letter-spacing-normal' => '0',
            'letter-spacing-wide' => '0.025em',

            // Spacing scale
            'space-1' => '4px',
            'space-2' => '8px',
            'space-3' => '12px',
            'space-4' => '16px',
            'space-5' => '20px',
            'space-6' => '24px',
            'space-8' => '32px',
            'space-10' => '40px',
            'space-12' => '48px',
            'space-16' => '64px',

            // Layout
            'container-width' => '1200px',
            'container-padding' => '24px',
            'grid-gap' => '24px',
            'border-radius-none' => '0px',
            'border-radius-sm' => '4px',
            'border-radius-md' => '8px',
            'border-radius-lg' => '12px',
            'border-radius-xl' => '16px',
            'border-radius-full' => '9999px',

            // Elevation
            'shadow-sm' => '0 1px 2px rgba(0,0,0,0.05)',
            'shadow-md' => '0 4px 6px -1px rgba(0,0,0,0.1)',
            'shadow-lg' => '0 10px 15px -3px rgba(0,0,0,0.1)',
            'shadow-xl' => '0 20px 25px -5px rgba(0,0,0,0.1)',

            // Motion
            'transition-fast' => '150ms ease',
            'transition-base' => '250ms ease',
            'transition-slow' => '400ms ease',
        ];
    }
}
