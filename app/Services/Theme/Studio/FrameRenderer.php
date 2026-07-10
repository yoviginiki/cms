<?php

namespace App\Services\Theme\Studio;

use App\Models\Theme;
use App\Services\Theme\ThemeCompiler;
use App\Services\Theme\ValueObjects\ResolvedTheme;
use Illuminate\Support\Facades\View;

class FrameRenderer
{
    public function __construct(
        private ThemeCompiler $compiler,
    ) {}

    /**
     * Render a frame as a complete HTML document for iframe embedding.
     *
     * @param string $css pre-rendered theme CSS. The caller passes the output
     *   of DesignTokenGenerator so the Studio iframe carries the EXACT same
     *   variable surface as the published page (fidelity). ResolvedTheme is
     *   still accepted for callers that only have tokens (compiled locally).
     */
    public function render(string $frameSlug, ResolvedTheme|string $cssOrResolved, bool $studio = false): string
    {
        $viewName = "theme-studio.frames.{$frameSlug}";
        if (!View::exists($viewName)) {
            return '<p>Frame not found: ' . e($frameSlug) . '</p>';
        }

        $css = is_string($cssOrResolved)
            ? $cssOrResolved
            : $this->compiler->renderCss($cssOrResolved);

        $frameHtml = View::make($viewName, ['themeStudio' => $studio])->render();

        return View::make('theme-studio.layout', [
            'themeCss' => $css,
            'frameHtml' => $frameHtml,
            'studio' => $studio,
        ])->render();
    }
}
