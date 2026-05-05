<?php

namespace App\Domain\Grid\Services;

use App\Models\Grid;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class GridRenderer
{
    public function __construct(
        private GridCssGenerator $cssGenerator,
        private PositionRenderer $positionRenderer,
    ) {}

    /**
     * Render the full grid HTML for a page or post.
     * Returns both the CSS and the grid HTML body.
     */
    public function render(Grid $grid, Page|Post $content, Site $site): array
    {
        $grid->load('positions');

        $css = $this->cssGenerator->generate($grid);

        $positionsHtml = '';
        foreach ($grid->positions as $position) {
            $posHtml = $this->positionRenderer->render($position, $content, $site);
            $extraClass = $position->css_class ? " {$position->css_class}" : '';
            $positionsHtml .= "  <div class=\"pos-{$position->area_name}{$extraClass}\">{$posHtml}</div>\n";
        }

        // Grid identifier comment for debugging
        $gridComment = "<!-- grid: {$grid->name} ({$grid->id}) -->\n";

        // Full-bleed grids get a wrapper div
        if ($grid->full_bleed) {
            $html = "{$gridComment}<div class=\"site-grid-wrap\" data-grid=\"{$grid->slug}\">\n";
            $html .= "  <div class=\"site-grid\">\n{$positionsHtml}  </div>\n";
            $html .= "</div>\n";
        } else {
            $html = "{$gridComment}<div class=\"site-grid\" data-grid=\"{$grid->slug}\">\n{$positionsHtml}</div>\n";
        }

        return [
            'css' => $css,
            'html' => $html,
        ];
    }
}
