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
        $mainEmitted = false;
        foreach ($grid->positions as $position) {
            $posHtml = $this->positionRenderer->render($position, $content, $site);
            $extraClass = $position->css_class ? " {$position->css_class}" : '';

            // Landmark elements per area (F3 follow-up — grid path parity with
            // the standard layout). Grid CSS is class-based (.pos-*, .site-grid
            // > *), so the wrapper tag is semantically free. Only the first
            // main/content area becomes <main> — a page allows exactly one.
            $tag = match ($position->area_name) {
                'header' => 'header',
                'footer' => 'footer',
                'nav' => 'nav',
                'sidebar' => 'aside',
                'main', 'content' => $mainEmitted ? 'div' : 'main',
                default => 'div',
            };
            $idAttr = '';
            if ($tag === 'main') {
                $mainEmitted = true;
                $idAttr = ' id="main-content"';
                // Posts publish inside an <article> landmark (F3)
                if ($content instanceof Post) {
                    $posHtml = '<article>' . $posHtml . '</article>';
                }
            }

            $positionsHtml .= "  <{$tag} class=\"pos-{$position->area_name}{$extraClass}\"{$idAttr}>{$posHtml}</{$tag}>\n";
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
