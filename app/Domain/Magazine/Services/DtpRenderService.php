<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;
use App\Support\Blocks\BlockStyle;

/**
 * Renders saved DTP magazine data as HTML.
 * No PDF export — HTML preview only.
 */
class DtpRenderService
{
    /**
     * Sanitize HTML — strip to tag allowlist and remove all attributes
     * except href (http/https only) on <a> tags.
     */
    private function sanitizeHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><b><i><u><em><strong><span><a><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><sub><sup><hr><div><img>');
        // Keep safe attributes: href (http/https only) and style (CSS properties only)
        return preg_replace_callback(
            '/<(\w+)(\s[^>]*)?>/',
            function ($m) {
                $tag = $m[1];
                $attrs = $m[2] ?? '';
                $safe = '';
                // Allow href on links
                if (strtolower($tag) === 'a' && preg_match('/href\s*=\s*"(https?:\/\/[^"]*)"/', $attrs, $hm)) {
                    $safe .= ' href="' . e($hm[1]) . '" rel="noopener noreferrer"';
                }
                // Allow src and alt on images (http/https and relative paths only)
                if (strtolower($tag) === 'img') {
                    if (preg_match('/src\s*=\s*"((?:https?:\/\/|\/)[^"]*)"/', $attrs, $sm)) {
                        $safe .= ' src="' . e($sm[1]) . '"';
                    }
                    if (preg_match('/alt\s*=\s*"([^"]*)"/', $attrs, $am)) {
                        $safe .= ' alt="' . e($am[1]) . '"';
                    }
                }
                // Allow style attribute — strip dangerous CSS
                if (preg_match('/style\s*=\s*"([^"]*)"/', $attrs, $sm)) {
                    $css = $sm[1];
                    // Remove dangerous CSS patterns
                    $css = preg_replace('/expression\s*\(|url\s*\(|javascript:|behavior:|@import|-moz-binding|data\s*:|position\s*:\s*fixed|position\s*:\s*absolute/i', '', $css);
                    if (trim($css)) {
                        $safe .= ' style="' . e($css) . '"';
                    }
                }
                return "<{$tag}{$safe}>";
            },
            $html
        ) ?: $html;
    }

    public function render(MagazineIssue $issue): array
    {
        $spreads = MagazineSpread::where('issue_id', $issue->id)->orderBy('spread_index')->get();
        $pages = MagazineDtpPage::where('issue_id', $issue->id)->orderBy('page_index')->get();
        $frames = MagazineFrame::where('issue_id', $issue->id)->where('visible', true)->orderBy('z_index')->get();

        $renderedSpreads = [];

        foreach ($spreads as $spread) {
            $spreadPages = $pages->where('spread_id', $spread->id)->sortBy('page_index');
            $renderedPages = [];

            foreach ($spreadPages as $page) {
                $pageFrames = $frames->where('page_id', $page->id)->sortBy('z_index');
                $renderedFrames = [];
                $hasSpreadImage = false;

                foreach ($pageFrames as $frame) {
                    $renderedFrames[] = $this->renderFrame($frame, $page);
                    if (($frame->metadata['spanMode'] ?? 'page') === 'spread') {
                        $hasSpreadImage = true;
                    }
                }

                $renderedPages[] = [
                    'id' => $page->id,
                    'index' => $page->page_index,
                    'width' => $page->width,
                    'height' => $page->height,
                    'background' => $page->background ?? ['color' => '#ffffff'],
                    'style' => $this->buildPageStyle($page, $hasSpreadImage),
                    'frames' => $renderedFrames,
                ];
            }

            $renderedSpreads[] = [
                'id' => $spread->id,
                'index' => $spread->spread_index,
                'name' => $spread->name,
                'pages' => $renderedPages,
            ];
        }

        $issueSettings = $issue->layout_final['issueSettings'] ?? [];

        // Re-group spreads by layout mode for viewer
        $layoutMode = $issueSettings['layoutMode'] ?? 'single';
        $coverMode = $issueSettings['coverMode'] ?? 'standalone';

        // For book mode, regroup pages into proper 2-page spreads
        if ($layoutMode === 'book') {
            $allRenderedPages = [];
            foreach ($renderedSpreads as $spread) {
                foreach ($spread['pages'] as $rp) {
                    $allRenderedPages[] = $rp;
                }
            }
            usort($allRenderedPages, fn($a, $b) => $a['index'] - $b['index']);

            $renderedSpreads = [];
            $i = 0;
            $total = count($allRenderedPages);
            while ($i < $total) {
                if ($i === 0 && $coverMode === 'standalone') {
                    // Cover page alone
                    $renderedSpreads[] = [
                        'id' => 'spread-cover',
                        'index' => count($renderedSpreads),
                        'name' => 'Cover',
                        'pages' => [$allRenderedPages[$i]],
                    ];
                    $i++;
                } else {
                    // Pair pages
                    $pair = [$allRenderedPages[$i]];
                    if ($i + 1 < $total) {
                        $pair[] = $allRenderedPages[$i + 1];
                    }
                    $renderedSpreads[] = [
                        'id' => 'spread-' . count($renderedSpreads),
                        'index' => count($renderedSpreads),
                        'name' => 'Spread ' . count($renderedSpreads),
                        'pages' => $pair,
                    ];
                    $i += 2;
                }
            }
        }

        return [
            'issue' => [
                'id' => $issue->id,
                'title' => $issue->title,
                'subtitle' => $issue->subtitle,
            ],
            'spreads' => $renderedSpreads,
            'pageCount' => $pages->count(),
            'frameCount' => $frames->count(),
            'layoutMode' => $layoutMode,
            'coverMode' => $coverMode,
        ];
    }

    private function renderFrame(MagazineFrame $frame, MagazineDtpPage $page): array
    {
        $content = is_array($frame->content) ? $frame->content : [];
        $style = $this->buildFrameStyle($frame);
        $type = $frame->frame_type->value ?? (string) $frame->frame_type;

        $html = match ($type) {
            'text' => $this->renderTextFrame($content),
            'image' => $this->renderImageFrame($content),
            'quote' => $this->renderQuoteFrame($content),
            'pageNumber' => $this->renderPageNumberFrame($page),
            'shape' => $this->renderShapeFrame($content),
            'line' => '<hr style="border:none;border-top:1px solid #333;margin:0;">',
            'decorative' => '<div style="width:100%;height:100%;"></div>',
            default => '<div></div>',
        };

        return [
            'id' => $frame->id,
            'type' => $type,
            'name' => $frame->name,
            'style' => $style,
            'html' => $html,
            'locked' => $frame->locked,
            'zIndex' => $frame->z_index,
        ];
    }

    /** Convert API asset URLs to public media URLs in HTML content */
    private function convertAssetUrls(string $html): string
    {
        $adminUrl = rtrim(config('app.url', 'https://sys.ensodo.eu'), '/');
        return preg_replace('#/api/v1/sites/([^/]+)/assets/([^/]+)/serve#', $adminUrl . '/media/$1/$2', $html) ?: $html;
    }

    private function renderTextFrame(array $content): string
    {
        $html = $this->sanitizeHtml($content['html'] ?? '');
        $html = $this->convertAssetUrls($html);
        return $html ?: '<p></p>';
    }

    private function renderImageFrame(array $content): string
    {
        $src = $content['src'] ?? '';
        $scheme = is_string($src) ? strtolower((string) parse_url($src, PHP_URL_SCHEME)) : '';
        $isRelative = is_string($src) && str_starts_with($src, '/');
        if (!$src || (!in_array($scheme, ['http', 'https'], true) && !$isRelative)) {
            return '<div style="width:100%;height:100%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px;">No image</div>';
        }

        $alt = e($content['alt'] ?? '');
        $fitMap = ['fill' => 'cover', 'fit' => 'contain', 'stretch' => 'fill', 'original' => 'none'];
        $objectFit = $fitMap[$content['fitMode'] ?? 'fill'] ?? 'cover';
        $fx = max(0, min(100, (int) ($content['focalPoint']['x'] ?? 50)));
        $fy = max(0, min(100, (int) ($content['focalPoint']['y'] ?? 50)));
        $opacity = max(0, min(100, (int) ($content['opacity'] ?? 100))) / 100;

        $style = "width:100%;height:100%;object-fit:{$objectFit};object-position:{$fx}% {$fy}%;display:block;";
        if ($opacity < 1) {
            $style .= "opacity:{$opacity};";
        }

        $imgTag = '<img src="' . e($src) . '" alt="' . $alt . '" style="' . $style . '" loading="lazy">';

        $caption = $content['caption'] ?? '';
        if ($caption) {
            $imgTag .= '<div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.5);padding:4px 8px;"><span style="color:#fff;font-size:9px;">' . e($caption) . '</span></div>';
        }

        return $imgTag;
    }

    private function renderQuoteFrame(array $content): string
    {
        $html = $this->sanitizeHtml($content['html'] ?? $content['text'] ?? '');
        $attribution = e($content['attribution'] ?? '');
        $out = '<blockquote style="margin:0;padding:12px 16px;border-left:3px solid #8b5cf6;font-style:italic;">' . ($html ?: '<p></p>') . '</blockquote>';
        if ($attribution) {
            $out .= '<p style="font-size:11px;color:#666;margin-top:4px;">' . $attribution . '</p>';
        }
        return $out;
    }

    private function renderPageNumberFrame(MagazineDtpPage $page): string
    {
        $num = $page->page_index + 1;
        return '<span style="font-size:11px;font-family:monospace;color:#999;">' . $num . '</span>';
    }

    private function renderShapeFrame(array $content): string
    {
        $fill = BlockStyle::safeColor($content['fillColor'] ?? '#e5e7eb') ?: '#e5e7eb';
        $radius = max(0, min(9999, (int) ($content['cornerRadius'] ?? 0)));
        return '<div style="width:100%;height:100%;background:' . $fill . ';border-radius:' . $radius . 'px;"></div>';
    }

    private function buildFrameStyle(MagazineFrame $frame): string
    {
        $x = round((float) $frame->x);
        $y = round((float) $frame->y);
        $w = max(1, round((float) $frame->width));
        $h = max(1, round((float) $frame->height));
        $r = round((float) $frame->rotation);
        $z = (int) $frame->z_index;
        $type = is_string($frame->frame_type) ? $frame->frame_type : ($frame->frame_type->value ?? 'text');
        $overflow = in_array($type, ['text', 'quote']) ? 'visible' : 'hidden';

        $style = "position:absolute;left:{$x}px;top:{$y}px;width:{$w}px;height:{$h}px;z-index:{$z};overflow:{$overflow};";
        if ($r !== 0) {
            $style .= "transform:rotate({$r}deg);";
        }
        return $style;
    }

    private function buildPageStyle(MagazineDtpPage $page, bool $hasSpreadImage = false): string
    {
        $w = max(1, (int) $page->width);
        $h = max(1, (int) $page->height);
        $bg = BlockStyle::safeColor($page->background['color'] ?? '#ffffff') ?: '#ffffff';
        $overflow = $hasSpreadImage ? 'visible' : 'hidden';
        return "position:relative;width:{$w}px;height:{$h}px;background:{$bg};overflow:{$overflow};";
    }
}
