<?php

namespace App\Services\Theme\Coverage;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Models\Theme;
use App\Services\Theme\ThemeResolver;
use App\Services\Theme\ValueObjects\ResolveRequest;
use Illuminate\Support\Facades\DB;

class ThemeCoverageService
{
    public function __construct(
        private BlockRegistry $blocks,
        private ThemeResolver $resolver,
    ) {}

    /**
     * Analyze a theme's coverage of all registered blocks.
     *
     * @param string $themeId
     * @param string $mode
     * @param string|null $context 'page', 'post', or null for all
     */
    public function analyze(string $themeId, string $mode = 'light', ?string $context = null): CoverageReport
    {
        // RLS policy allows site_id IS NULL (system themes)
        $theme = Theme::find($themeId);

        if (!$theme || !$theme->document) {
            return new CoverageReport($themeId, $mode);
        }

        // Resolve from document directly (works for both system and site themes)
        $merger = new \App\Services\Theme\TokenMerger();
        $refs = new \App\Services\Theme\ReferenceResolver();
        $flat = $refs->flatten($merger->merge([$theme->document]));
        $resolved = new \App\Services\Theme\ValueObjects\ResolvedTheme($flat, hash('sha256', json_encode($flat)));

        $report = new CoverageReport($themeId, $mode);
        $allBlocks = $this->blocks->getAllTypes();

        foreach ($allBlocks as $type => $meta) {
            $definition = $this->blocks->get($type);
            if (!$definition) continue;

            $blockDef = $definition['definition'] ?? null;
            if (!$blockDef) continue;

            // Check if the block defines a tokenManifest (the new method)
            // Since existing blocks don't have this method yet, we skip gracefully
            if (!method_exists($blockDef, 'tokenManifest')) continue;

            $manifest = $blockDef->tokenManifest();

            foreach ($manifest->required() as $path => $purpose) {
                if (!$resolved->has($path)) {
                    $report->addGap(
                        block: $type,
                        tokenPath: $path,
                        severity: Severity::Critical,
                        purpose: $purpose,
                    );
                }
            }

            foreach ($manifest->optionalWithFallbacks() as $path => $meta) {
                $hasToken = $resolved->has($path);
                $hasFallback = $resolved->has($meta['fallback']);

                if (!$hasToken && !$hasFallback) {
                    $report->addGap(
                        block: $type,
                        tokenPath: $path,
                        severity: Severity::Warning,
                        purpose: $meta['purpose'],
                        fallback: $meta['fallback'],
                    );
                } elseif (!$hasToken && $hasFallback) {
                    $report->addFallback($type, $path, $meta['fallback']);
                }
            }
        }

        return $report;
    }
}
