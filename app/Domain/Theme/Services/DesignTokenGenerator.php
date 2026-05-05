<?php

namespace App\Domain\Theme\Services;

use App\Models\Site;
use App\Models\Theme;

class DesignTokenGenerator
{
    /**
     * Generate CSS custom properties from theme config + customizations.
     */
    public function generate(Site $site): string
    {
        $theme = $site->theme;
        if (!$theme) return '';

        $defaults = $this->getDefaults();
        $themeTokens = $theme->config['tokens'] ?? [];
        $customizations = $this->getCustomizations($site, $theme);

        // Merge: defaults → theme → customizations
        $tokens = array_merge($defaults, $themeTokens, $customizations);

        $css = ":root {\n";
        foreach ($tokens as $key => $value) {
            $css .= "  --{$key}: {$value};\n";
        }
        $css .= "}\n";

        // Add font imports
        $css .= $this->generateFontImports($tokens);

        return $css;
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
