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
     */
    public function render(string $frameSlug, ResolvedTheme $resolved, bool $studio = false): string
    {
        $viewName = "theme-studio.frames.{$frameSlug}";
        if (!View::exists($viewName)) {
            return '<p>Frame not found: ' . e($frameSlug) . '</p>';
        }

        $css = $this->compiler->renderCss($resolved);

        $frameHtml = View::make($viewName, ['themeStudio' => $studio])->render();

        return View::make('theme-studio.layout', [
            'themeCss' => $css,
            'frameHtml' => $frameHtml,
            'studio' => $studio,
        ])->render();
    }
}
