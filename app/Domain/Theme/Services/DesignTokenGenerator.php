<?php

namespace App\Domain\Theme\Services;

use App\Models\Site;
use App\Models\Theme;

class DesignTokenGenerator
{
    /**
     * Generate CSS custom properties from theme config + customizations.
     */
    /** Map from W3C token paths to CSS variable names. */
    private const W3C_TO_CSS = [
        'semantic.color.brand' => 'color-primary',
        'semantic.color.accent' => 'color-accent',
        'semantic.color.success' => 'color-success',
        'semantic.color.warning' => 'color-warning',
        'semantic.color.danger' => 'color-danger',
        'semantic.color.background.canvas' => 'color-bg',
        'semantic.color.background.surface' => 'color-bg-alt',
        'semantic.color.background.raised' => 'color-bg',
        'semantic.color.background.overlay' => null,
        'semantic.color.text.body' => 'color-text',
        'semantic.color.text.heading' => 'color-bg-inverse',
        'semantic.color.text.muted' => 'color-text-muted',
        'semantic.color.text.link' => 'color-accent',
        'semantic.color.text.inverse' => 'color-text-inverse',
        'semantic.color.border.default' => 'color-border',
        'semantic.color.border.subtle' => 'color-border-light',
        'semantic.color.border.strong' => null,
        'semantic.font.family.display' => 'font-heading',
        'semantic.font.family.body' => 'font-body',
        'semantic.font.family.mono' => 'font-mono',
        'semantic.font.size.xs' => null,
        'semantic.font.size.sm' => 'font-size-sm',
        'semantic.font.size.base' => 'font-size-base',
        'semantic.font.size.lg' => 'font-size-lg',
        'semantic.font.size.xl' => 'font-size-xl',
        'semantic.font.size.2xl' => 'font-size-2xl',
        'semantic.font.size.3xl' => 'font-size-3xl',
        'semantic.font.size.4xl' => null,
        'semantic.font.size.5xl' => null,
        'semantic.size.radius.none' => null,
        'semantic.size.radius.sm' => 'border-radius-sm',
        'semantic.size.radius.md' => 'border-radius-md',
        'semantic.size.radius.lg' => 'border-radius-lg',
        'semantic.size.radius.xl' => null,
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

        $css = ":root {\n";
        foreach ($tokens as $key => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            $css .= "  --{$key}: {$value};\n";
        }
        $css .= "}\n";

        $css .= $this->generateFontImports($tokens);

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
            $cssName = self::W3C_TO_CSS[$path] ?? null;
            if (!$cssName) continue;
            if (is_array($value)) {
                $value = "'" . implode("', '", $value) . "'";
            }
            $result[$cssName] = $value;
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
     * Generate Google Fonts @import for heading and body fonts.
     */
    private function generateFontImports(array $tokens): string
    {
        $fonts = [];
        $headingFont = $tokens['font-heading'] ?? null;
        $bodyFont = $tokens['font-body'] ?? null;

        if ($headingFont && !str_contains($headingFont, 'system-ui')) {
            $clean = trim(explode(',', $headingFont)[0], "' \"");
            $fonts[] = urlencode($clean) . ':wght@400;600;700';
        }
        if ($bodyFont && !str_contains($bodyFont, 'system-ui') && $bodyFont !== $headingFont) {
            $clean = trim(explode(',', $bodyFont)[0], "' \"");
            $fonts[] = urlencode($clean) . ':wght@400;500;600';
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
            'color-text-muted' => '#64748b',
            'color-text-inverse' => '#ffffff',
            'color-bg' => '#ffffff',
            'color-bg-alt' => '#f8fafc',
            'color-bg-inverse' => '#0f172a',
            'color-border' => '#e2e8f0',
            'color-border-light' => '#f1f5f9',
            'color-success' => '#22c55e',
            'color-warning' => '#f59e0b',
            'color-danger' => '#ef4444',
            'color-info' => '#3b82f6',

            // Typography
            'font-heading' => "'Inter', system-ui, -apple-system, sans-serif",
            'font-body' => "'Inter', system-ui, -apple-system, sans-serif",
            'font-mono' => "ui-monospace, 'SF Mono', monospace",
            'font-size-base' => 'clamp(14px, 1vw + 12px, 18px)',
            'font-size-sm' => '0.875rem',
            'font-size-lg' => '1.125rem',
            'font-size-xl' => '1.25rem',
            'font-size-2xl' => '1.5rem',
            'font-size-3xl' => '2rem',
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
            'border-radius-sm' => '4px',
            'border-radius-md' => '8px',
            'border-radius-lg' => '12px',
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
