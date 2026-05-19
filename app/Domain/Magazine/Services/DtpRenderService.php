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
        $html = strip_tags($html, '<p><br><b><i><u><em><strong><span><a><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><sub><sup><hr><div>');
        // Remove all attributes except safe href
        return preg_replace_callback(
            '/<(\w+)(\s[^>]*)?>/',
            function ($m) {
                $tag = $m[1];
                $attrs = $m[2] ?? '';
                $safe = '';
                if (strtolower($tag) === 'a' && preg_match('/href\s*=\s*"(https?:\/\/[^"]*)"/', $attrs, $hm)) {
                    $safe = ' href="' . e($hm[1]) . '" rel="noopener noreferrer"';
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

                foreach ($pageFrames as $frame) {
                    $renderedFrames[] = $this->renderFrame($frame, $page);
                }

                $renderedPages[] = [
                    'id' => $page->id,
                    'index' => $page->page_index,
                    'width' => $page->width,
                    'height' => $page->height,
                    'background' => $page->background ?? ['color' => '#ffffff'],
                    'style' => $this->buildPageStyle($page),
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

        return [
            'issue' => [
                'id' => $issue->id,
                'title' => $issue->title,
                'subtitle' => $issue->subtitle,
            ],
            'spreads' => $renderedSpreads,
            'pageCount' => $pages->count(),
            'frameCount' => $frames->count(),
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

    private function renderTextFrame(array $content): string
    {
        $html = $this->sanitizeHtml($content['html'] ?? '');
        return $html ?: '<p></p>';
    }

    private function renderImageFrame(array $content): string
    {
        $src = $content['src'] ?? '';
        if (!$src || !preg_match('#^https?://#i', $src)) {
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

        $style = "position:absolute;left:{$x}px;top:{$y}px;width:{$w}px;height:{$h}px;z-index:{$z};overflow:hidden;";
        if ($r !== 0) {
            $style .= "transform:rotate({$r}deg);";
        }
        return $style;
    }

    private function buildPageStyle(MagazineDtpPage $page): string
    {
        $w = max(1, (int) $page->width);
        $h = max(1, (int) $page->height);
        $bg = BlockStyle::safeColor($page->background['color'] ?? '#ffffff') ?: '#ffffff';
        return "position:relative;width:{$w}px;height:{$h}px;background:{$bg};overflow:hidden;";
    }
}
