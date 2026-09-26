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
    /**
     * @param bool $markAreas preview only: tag every area with data-sp-* so the
     *        admin can outline it and open its section (never in published HTML)
     */
    public function render(Grid $grid, Page|Post $content, Site $site, bool $markAreas = false): array
    {
        $grid->load('positions');
        $sectionNames = $markAreas
            ? \App\Models\GlobalSection::where('site_id', $site->id)->pluck('name', 'id')
            : collect();

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
            // A position's content can already carry that landmark itself — a
            // footer-location menu renders its own <footer>, a fullscreen embed
            // its own <main>. Wrapping it again nests the same landmark, which
            // is invalid HTML and leaves assistive tech with two ambiguous
            // contentinfo/main regions. Grid CSS is class-based (.pos-*), so
            // degrading the wrapper to a <div> changes nothing visually.
            $nested = $tag !== 'div' && preg_match('#<' . $tag . '[\s>]#i', $posHtml) === 1;

            $idAttr = '';
            if ($tag === 'main') {
                $mainEmitted = true;
                // The skip link targets this id; it stays on the wrapper whether
                // or not the wrapper had to degrade to a div.
                $idAttr = ' id="main-content"';
                // Posts publish inside an <article> landmark (F3) — but not when
                // the content already brought its own <main> to sit inside.
                if ($content instanceof Post && !$nested) {
                    $posHtml = '<article>' . $posHtml . '</article>';
                }
            }

            if ($nested) {
                $tag = 'div';
            }

            $markAttr = $markAreas ? $this->areaMarkers($position, $content, $sectionNames) : '';

            $positionsHtml .= "  <{$tag} class=\"pos-{$position->area_name}{$extraClass}\"{$idAttr}{$markAttr}>{$posHtml}</{$tag}>\n";
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

    /** data-sp-* attributes describing an area (and its effective section) for the admin overlay. */
    private function areaMarkers(\App\Models\GridPosition $position, Page|Post $content, $sectionNames): string
    {
        $sectionId = $position->type === 'section' ? ($position->config_json['section_id'] ?? null) : null;
        $override = $content instanceof Post
            ? $position->getOverrideForPost($content->id)
            : $position->getOverrideForPage($content->id);
        if ($override && !empty($override->content_json['section_id'])) {
            $sectionId = $override->content_json['section_id'];
        }

        $attrs = [
            'data-sp-area' => $position->area_name,
            'data-sp-position' => $position->id,
            'data-sp-type' => $position->type,
            'data-sp-label' => $position->label ?: $position->area_name,
        ];
        if ($sectionId) {
            $attrs['data-sp-section'] = $sectionId;
            $attrs['data-sp-section-name'] = (string) ($sectionNames[$sectionId] ?? '');
        }
        if ($override) {
            $attrs['data-sp-override'] = !empty($override->content_json['hidden']) ? 'hidden' : 'section';
        }

        return implode('', array_map(fn ($k, $v) => ' ' . $k . '="' . e($v) . '"', array_keys($attrs), $attrs));
    }
}
