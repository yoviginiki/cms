<?php

namespace App\Services\Theme;

use App\Models\Site;
use App\Models\ThemeVersion;
use App\Services\Theme\ValueObjects\ResolveRequest;
use App\Services\Theme\ValueObjects\ResolvedTheme;
use Illuminate\Support\Facades\Storage;

/**
 * Compiles a resolved theme into a CSS artifact for static publishing.
 */
class ThemeCompiler
{
    public function __construct(
        private ThemeResolver $resolver,
    ) {}

    /**
     * Compile theme for a site+mode, store CSS artifact, create ThemeVersion record.
     */
    public function compile(string $siteId, string $mode = 'light'): ?ThemeVersion
    {
        $site = Site::findOrFail($siteId);

        $resolved = $this->resolver->resolveFresh(new ResolveRequest(
            tenantId: $site->tenant_id,
            siteId: $siteId,
            mode: $mode,
        ));

        if (empty($resolved->tokens)) {
            return null;
        }

        $css = $this->renderCss($resolved, $mode);
        $hash = hash('sha256', $css);
        $path = "themes/site-{$siteId}/theme.{$hash}.css";

        // Store to the publish disk (or local storage)
        $disk = Storage::disk('local');
        $disk->put($path, $css);

        // Also copy to public_html for static serving
        $publicPath = base_path('../../public_html/' . $path);
        $publicDir = dirname($publicPath);
        if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);
        file_put_contents($publicPath, $css);

        return ThemeVersion::create([
            'tenant_id' => $site->tenant_id,
            'theme_id' => $site->active_theme_id ?? '',
            'site_id' => $siteId,
            'mode' => $mode,
            'resolved_document' => $resolved->toArray(),
            'content_hash' => $hash,
            'css_artifact_path' => $path,
            'css_artifact_size' => strlen($css),
        ]);
    }

    /**
     * Render resolved tokens as CSS.
     */
    public function renderCss(ResolvedTheme $theme, string $mode = 'light'): string
    {
        $lines = [];

        foreach ($theme->tokens as $path => $value) {
            $varName = '--' . str_replace('.', '-', $path);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $lines[] = "  {$varName}: {$value};";
        }

        $selector = $mode === 'light' ? ':root' : "[data-theme=\"{$mode}\"]";
        $css = "@layer theme {\n  {$selector} {\n" . implode("\n", $lines) . "\n  }\n}\n";

        return $css;
    }
}
