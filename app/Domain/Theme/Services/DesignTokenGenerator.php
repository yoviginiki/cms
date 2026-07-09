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

        // Typography extras
        'semantic.font.lineHeight.body' => 'line-height-body',
        'semantic.font.lineHeight.heading' => 'line-height-heading',
        'semantic.font.letterSpacing.body' => 'letter-spacing-body',
        'semantic.font.letterSpacing.heading' => 'letter-spacing-heading',
        'semantic.font.weight.heading' => 'heading-weight',

        // Link behavior
        'semantic.color.text.link.hoverOpacity' => 'link-hover-opacity',

        // Navigation
        'semantic.nav.height' => 'nav-height',
        'semantic.nav.bgBlur' => 'nav-bg-blur',
        'semantic.nav.fontSize' => 'nav-font-size',
        'semantic.nav.fontWeight' => 'nav-font-weight',
        'semantic.nav.letterSpacing' => 'nav-tracking',
        'semantic.nav.textTransform' => 'nav-transform',
        'semantic.nav.gap' => 'nav-gap',
        'semantic.nav.logoSize' => 'nav-logo-size',
        'semantic.nav.logoWeight' => 'nav-logo-weight',
        'semantic.nav.logoTracking' => 'nav-logo-tracking',
        'semantic.nav.logoTransform' => 'nav-logo-transform',
        'semantic.nav.padding' => 'nav-padding',

        // Nav overlay mode
        'semantic.nav.overlayBg' => 'nav-overlay-bg',
        'semantic.nav.overlayColor' => 'nav-overlay-color',
        'semantic.nav.overlayHoverColor' => 'nav-overlay-hover-color',
        'semantic.nav.overlayFontSize' => 'nav-overlay-font-size',
        'semantic.nav.overlayFontWeight' => 'nav-overlay-font-weight',
        'semantic.nav.overlayTracking' => 'nav-overlay-tracking',
        'semantic.nav.overlayTransform' => 'nav-overlay-transform',
        'semantic.nav.overlayGap' => 'nav-overlay-gap',
        'semantic.nav.overlaySubFontSize' => 'nav-overlay-sub-font-size',

        // Footer
        'semantic.footer.bg' => 'footer-bg',
        'semantic.footer.color' => 'footer-color',
        'semantic.footer.borderColor' => 'footer-border-color',

        // Buttons
        'semantic.btn.bg' => 'btn-bg',
        'semantic.btn.color' => 'btn-color',
        'semantic.btn.border' => 'btn-border',
        'semantic.btn.hoverBg' => 'btn-hover-bg',
        'semantic.btn.hoverColor' => 'btn-hover-color',
        'semantic.btn.padding' => 'btn-padding',
        'semantic.btn.fontWeight' => 'btn-font-weight',
        'semantic.btn.tracking' => 'btn-tracking',
        'semantic.btn.transform' => 'btn-transform',
        'semantic.btn.radius' => 'btn-radius',

        // Cards
        'semantic.card.border' => 'card-border',

        // Content
        'semantic.content.maxWidth' => 'content-max-width',
        'semantic.content.proseMaxWidth' => 'prose-max-width',
    ];

    public function generate(Site $site): string
    {
        return $this->generateForTheme($site->theme, $site);
    }

    /**
     * Generate the published CSS for a SPECIFIC theme in a site's context.
     * `generate()` uses the site's active theme; the Theme Studio preview
     * passes the theme being edited so its iframe emits the EXACT same CSS
     * variable surface (semantic + legacy aliases + defaults + font imports)
     * that the published page will — no second generator, no fidelity gap.
     */
    public function generateForTheme(?Theme $theme, Site $site): string
    {
        // A themeless site still needs the default token set — every block's
        // var(--…) reference would otherwise fall back to its inline literal,
        // producing an unstyled page. Defaults are the Stillopress house style.
        $defaults = $this->getDefaults();
        $themeTokens = $theme ? ($theme->config['tokens'] ?? []) : [];
        $customizations = $theme ? $this->getCustomizations($site, $theme) : [];

        // Merge: defaults → config tokens → customizations
        $tokens = array_merge($defaults, $themeTokens, $customizations);

        // Bridge W3C document tokens → CSS variables (studio edits affect published site)
        if ($theme && $theme->document) {
            $docTokens = $this->resolveDocumentTokens($theme->document);
            $tokens = array_merge($tokens, $docTokens);
        }

        // Google Font @import must come first per CSS spec
        $css = $this->generateFontImports($tokens);

        // Custom font @font-face rules (after @import, before :root)
        $css .= $this->generateCustomFontFaces($site);

        $css .= ":root {\n";
        foreach ($tokens as $key => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            // Sanitize: strip characters that could break out of CSS declarations
            $safeKey = preg_replace('/[^a-zA-Z0-9\-_]/', '', $key);
            $safeValue = preg_replace('/[{}<>;\\\\]/', '', (string) $value);
            $css .= "  --{$safeKey}: {$safeValue};\n";
        }
        // Inject global style settings from site settings
        $settings = $site->settings ?? [];
        $globalVars = [];
        if (!empty($settings['global_font_family'])) $globalVars['font-family-base'] = preg_replace('/[^a-zA-Z0-9\s,\'-]/', '', $settings['global_font_family']);
        if (!empty($settings['global_font_size'])) $globalVars['font-size-base'] = preg_replace('/[^a-zA-Z0-9.%]/', '', $settings['global_font_size']);
        if (!empty($settings['global_line_height'])) $globalVars['line-height-base'] = preg_replace('/[^0-9.]/', '', $settings['global_line_height']);
        if (!empty($settings['global_text_color'])) $globalVars['color-text'] = preg_replace('/[^a-fA-F0-9#]/', '', $settings['global_text_color']);
        if (!empty($settings['global_bg_color'])) $globalVars['color-bg'] = preg_replace('/[^a-fA-F0-9#]/', '', $settings['global_bg_color']);
        if (!empty($settings['global_link_color'])) $globalVars['color-primary'] = preg_replace('/[^a-fA-F0-9#]/', '', $settings['global_link_color']);
        if (!empty($settings['global_container_width'])) $globalVars['container-width'] = preg_replace('/[^a-zA-Z0-9.%]/', '', $settings['global_container_width']);
        if (!empty($settings['global_container_padding'])) $globalVars['container-padding'] = preg_replace('/[^a-zA-Z0-9.%]/', '', $settings['global_container_padding']);
        foreach ($globalVars as $k => $v) {
            $css .= "  --{$k}: {$v};\n";
        }

        $css .= "}\n";

        // Apply global body styles
        if (!empty($globalVars)) {
            $css .= "body {\n";
            if (isset($globalVars['font-family-base'])) $css .= "  font-family: var(--font-family-base);\n";
            if (isset($globalVars['font-size-base'])) $css .= "  font-size: var(--font-size-base);\n";
            if (isset($globalVars['line-height-base'])) $css .= "  line-height: var(--line-height-base);\n";
            if (isset($globalVars['color-text'])) $css .= "  color: var(--color-text);\n";
            if (isset($globalVars['color-bg'])) $css .= "  background-color: var(--color-bg);\n";
            $css .= "}\n";
            if (isset($globalVars['color-primary'])) {
                $css .= "a { color: var(--color-primary); }\n";
            }
        }

        // Site Background from theme document
        if ($theme && $theme->document && isset($theme->document['siteBackground'])) {
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
    /**
     * Generate @font-face rules for custom uploaded fonts.
     */
    private function generateCustomFontFaces(\App\Models\Site $site): string
    {
        $fonts = $site->settings['custom_fonts'] ?? [];
        if (empty($fonts)) return '';

        $css = '';
        foreach ($fonts as $font) {
            $family = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $font['family'] ?? '');
            $weight = max(100, min(900, (int) ($font['weight'] ?? 400)));
            $fontStyle = in_array($font['style'] ?? '', ['normal', 'italic']) ? $font['style'] : 'normal';
            $format = preg_replace('/[^a-z0-9]/', '', $font['format'] ?? 'truetype');
            $filename = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $font['filename'] ?? '');

            if (!$family || !$filename) continue;

            $siteId = $site->id;
            $css .= "@font-face {\n";
            $css .= "  font-family: '{$family}';\n";
            $css .= "  font-weight: {$weight};\n";
            $css .= "  font-style: {$fontStyle};\n";
            $fontSlug = pathinfo($filename, PATHINFO_FILENAME); // no extension = bypasses nginx static catch
            // /serve-font/ for admin preview, /fonts/ for published static site
            $css .= "  src: url('/serve-font/{$siteId}/{$fontSlug}') format('{$format}'), url('/fonts/{$filename}') format('{$format}');\n";
            $css .= "  font-display: swap;\n";
            $css .= "}\n";
        }
        return $css;
    }

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

            // Typography extras
            'line-height-body' => '1.6',
            'line-height-heading' => '1.25',
            'letter-spacing-body' => '0',
            'letter-spacing-heading' => '0',
            'heading-weight' => '700',

            // Link behavior
            'link-hover-opacity' => '1',

            // Navigation
            'nav-height' => 'auto',
            'nav-bg-blur' => '20px',
            'nav-font-size' => '12px',
            'nav-font-weight' => '500',
            'nav-tracking' => '0.12em',
            'nav-transform' => 'uppercase',
            'nav-gap' => '28px',
            'nav-logo-size' => '14px',
            'nav-logo-weight' => '600',
            'nav-logo-tracking' => '0.1em',
            'nav-logo-transform' => 'none',
            'nav-padding' => '14px 0',

            // Footer
            'footer-bg' => 'var(--color-bg-alt, #f8fafc)',
            'footer-color' => 'var(--color-text-muted, #64748b)',
            'footer-border-color' => 'var(--color-border-light, #f1f5f9)',

            // Buttons
            'btn-bg' => 'var(--color-primary, #3b82f6)',
            'btn-color' => '#ffffff',
            'btn-border' => 'transparent',
            'btn-hover-bg' => 'var(--color-primary-dark, #2563eb)',
            'btn-hover-color' => '#ffffff',
            'btn-padding' => '12px 24px',
            'btn-font-weight' => '600',
            'btn-tracking' => '0.12em',
            'btn-transform' => 'uppercase',
            'btn-radius' => 'var(--border-radius-md, 8px)',

            // Content
            'content-max-width' => '800px',
            'prose-max-width' => '65ch',

            // Form inputs — themes can now restyle every field consistently
            'input-bg' => 'var(--color-bg, #ffffff)',
            'input-color' => 'var(--color-text, #1e293b)',
            'input-border' => 'var(--color-border-strong, #94a3b8)',
            'input-border-focus' => 'var(--color-primary, #3b82f6)',
            'input-radius' => 'var(--border-radius-sm, 4px)',
            'input-placeholder' => 'var(--color-text-muted, #64748b)',

            // Code block theme (used by the code block; kept dark by default)
            'code-bg' => '#1e293b',
            'code-color' => '#e2e8f0',
            'code-comment' => '#94a3b8',
            'code-border' => '#475569',

            // Chart / data-series palette (6-hue rotation)
            'chart-1' => 'var(--color-primary, #3b82f6)',
            'chart-2' => '#f59e0b',
            'chart-3' => '#22c55e',
            'chart-4' => '#ef4444',
            'chart-5' => '#8b5cf6',
            'chart-6' => '#06b6d4',
        ];
    }
}
