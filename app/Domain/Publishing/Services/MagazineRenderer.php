<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Magazine\Services\MagazineService;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class MagazineRenderer
{
    public function __construct(
        private MagazineService $magazineService,
    ) {}

    public function render(Page|Post $content, Site $site): string
    {
        $doc = $this->magazineService->getDocument($content);

        $html = '';
        foreach ($doc['pages'] as $magPage) {
            $pageW = $magPage['page_size']['width'] ?? 595;
            $pageH = $magPage['page_size']['height'] ?? 842;
            $pageNum = $magPage['page_number'];

            $html .= '<div class="mag-page" style="position:relative;width:' . $pageW . 'pt;max-width:100%;aspect-ratio:' . $pageW . '/' . $pageH . ';margin:0 auto 24px;overflow:hidden;background:' . ($magPage['background_color'] ?? '#fff') . '">';

            // Get elements for this page, ordered by z_index
            $elements = collect($doc['elements'])->where('page_number', $pageNum)->sortBy('z_index');

            foreach ($elements as $el) {
                $html .= $this->renderElement($el);
            }

            $html .= '</div>';
        }

        // Wrap with responsive scaling
        $html = '<div class="mag-document">' . $html . '</div>';
        $html .= '<style>.mag-document{max-width:100%;margin:0 auto}.mag-page{box-shadow:0 2px 8px rgba(0,0,0,0.1)}</style>';

        return $html;
    }

    private function renderElement(array $el): string
    {
        $style = 'position:absolute;';
        $style .= 'left:' . $el['x'] . 'pt;top:' . $el['y'] . 'pt;';
        $style .= 'width:' . $el['width'] . 'pt;height:' . $el['height'] . 'pt;';
        if ($el['rotation'] ?? 0) {
            $style .= 'transform:rotate(' . $el['rotation'] . 'deg);';
        }
        if ($el['z_index'] ?? 0) {
            $style .= 'z-index:' . $el['z_index'] . ';';
        }

        // Apply visual styles
        $s = $el['style'] ?? [];
        if (!empty($s['fill']['color'])) {
            $style .= 'background-color:' . $s['fill']['color'] . ';';
        }
        if (isset($s['opacity']) && $s['opacity'] < 1) {
            $style .= 'opacity:' . $s['opacity'] . ';';
        }
        if (!empty($s['cornerRadius'])) {
            $cr = $s['cornerRadius'];
            $style .= 'border-radius:' . ($cr['tl'] ?? 0) . 'px ' . ($cr['tr'] ?? 0) . 'px ' . ($cr['br'] ?? 0) . 'px ' . ($cr['bl'] ?? 0) . 'px;';
        }
        if (!empty($s['shadow'])) {
            $sh = $s['shadow'];
            $style .= 'box-shadow:' . ($sh['x'] ?? 0) . 'px ' . ($sh['y'] ?? 2) . 'px ' . ($sh['blur'] ?? 4) . 'px ' . ($sh['spread'] ?? 0) . 'px ' . ($sh['color'] ?? 'rgba(0,0,0,0.1)') . ';';
        }
        if (!empty($s['stroke']['width']) && $s['stroke']['width'] > 0) {
            $style .= 'border:' . $s['stroke']['width'] . 'px solid ' . ($s['stroke']['color'] ?? '#000') . ';';
        }

        $type = $el['type'];
        $data = $el['data'] ?? [];
        $typo = $el['typography'] ?? [];

        // Typography styles for text elements
        $textStyle = '';
        if (!empty($typo)) {
            if (!empty($typo['fontFamily'])) {
                $textStyle .= 'font-family:' . $typo['fontFamily'] . ';';
            }
            if (!empty($typo['fontSize'])) {
                $textStyle .= 'font-size:' . $typo['fontSize'] . 'pt;';
            }
            if (!empty($typo['fontWeight'])) {
                $textStyle .= 'font-weight:' . $typo['fontWeight'] . ';';
            }
            if (!empty($typo['lineHeight'])) {
                $textStyle .= 'line-height:' . $typo['lineHeight'] . ';';
            }
            if (!empty($typo['letterSpacing'])) {
                $textStyle .= 'letter-spacing:' . $typo['letterSpacing'] . 'em;';
            }
            if (!empty($typo['textAlign'])) {
                $textStyle .= 'text-align:' . $typo['textAlign'] . ';';
            }
            if (!empty($typo['textColor'])) {
                $textStyle .= 'color:' . $typo['textColor'] . ';';
            }
            if (!empty($typo['textTransform'])) {
                $textStyle .= 'text-transform:' . $typo['textTransform'] . ';';
            }
        }

        $content = '';

        switch ($type) {
            case 'text_frame':
            case 'headline_frame':
            case 'pullquote_frame':
            case 'caption_frame':
            case 'footnote_frame':
            case 'marginalia_frame':
                $cols = $data['columnsInFrame'] ?? 1;
                $colGap = $data['columnGap'] ?? 12;
                $inset = $data['textInset'] ?? ['top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8];
                $colStyle = $cols > 1 ? "column-count:{$cols};column-gap:{$colGap}pt;" : '';
                $padStyle = "padding:{$inset['top']}pt {$inset['right']}pt {$inset['bottom']}pt {$inset['left']}pt;";
                $rawHtml = strip_tags($data['content'] ?? '', '<p><br><b><i><u><em><strong><span><a><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><sub><sup><hr><div><img>');
                $content = '<div style="' . $textStyle . $colStyle . $padStyle . 'width:100%;height:100%;overflow:hidden;">' . $rawHtml . '</div>';
                break;

            case 'image_frame':
            case 'circular_image':
            case 'polygon_image':
            case 'fullbleed_image':
                $src = $data['src'] ?? '';
                $alt = e($data['alt'] ?? '');
                $fit = $data['fit'] ?? 'cover';
                $focal = $data['focalPoint'] ?? ['x' => 0.5, 'y' => 0.5];
                $imgStyle = "width:100%;height:100%;object-fit:{$fit};object-position:" . ($focal['x'] * 100) . '% ' . ($focal['y'] * 100) . '%;';
                if ($type === 'circular_image') {
                    $style .= 'border-radius:50%;overflow:hidden;';
                }
                // Filters
                $filters = $data['filters'] ?? [];
                $filterCSS = '';
                if (!empty($filters['brightness']) && $filters['brightness'] != 100) {
                    $filterCSS .= 'brightness(' . ($filters['brightness'] / 100) . ')';
                }
                if (!empty($filters['contrast']) && $filters['contrast'] != 100) {
                    $filterCSS .= ' contrast(' . ($filters['contrast'] / 100) . ')';
                }
                if (!empty($filters['saturation']) && $filters['saturation'] != 100) {
                    $filterCSS .= ' saturate(' . ($filters['saturation'] / 100) . ')';
                }
                if (!empty($filters['grayscale'])) {
                    $filterCSS .= ' grayscale(1)';
                }
                if ($filterCSS) {
                    $imgStyle .= "filter:{$filterCSS};";
                }
                $content = $src ? '<img src="' . e($src) . '" alt="' . $alt . '" style="' . $imgStyle . '" loading="lazy">' : '<div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#aaa;">Image</div>';
                break;

            case 'ellipse':
                $style .= 'border-radius:50%;';
                break;

            case 'line':
                $x2 = $data['x2'] ?? $el['width'];
                $y2 = $data['y2'] ?? $el['height'];
                $strokeW = $data['strokeWidth'] ?? 1;
                $strokeC = $data['strokeColor'] ?? '#000';
                $content = '<svg width="100%" height="100%" style="overflow:visible"><line x1="0" y1="0" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . e($strokeC) . '" stroke-width="' . $strokeW . '"/></svg>';
                break;

            case 'video_frame':
                $url = $data['url'] ?? '';
                if (str_contains($url, 'youtube') || str_contains($url, 'youtu.be')) {
                    preg_match('/(?:v=|\/)([\w-]{11})/', $url, $m);
                    $vid = $m[1] ?? '';
                    $content = '<iframe src="https://www.youtube-nocookie.com/embed/' . $vid . '?rel=0" style="width:100%;height:100%;border:none" loading="lazy" allowfullscreen></iframe>';
                }
                break;

            case 'button':
                $text = e($data['text'] ?? 'Button');
                $url = e($data['url'] ?? '#');
                $variant = $data['variant'] ?? 'solid';
                $btnStyle = $variant === 'solid'
                    ? 'background:#000;color:#fff;'
                    : ($variant === 'outline'
                        ? 'border:1px solid #000;color:#000;background:transparent;'
                        : 'color:#000;background:transparent;');
                $content = '<a href="' . $url . '" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;text-decoration:none;font-size:14px;font-weight:500;' . $btnStyle . '">' . $text . '</a>';
                break;

            case 'page_number':
                $format = $data['format'] ?? 'decimal';
                $prefix = $data['prefix'] ?? '';
                $suffix = $data['suffix'] ?? '';
                $num = $el['page_number'] ?? 1;
                $content = '<span style="' . $textStyle . '">' . $prefix . $num . $suffix . '</span>';
                break;

            case 'svg_icon':
                $color = $data['color'] ?? '#000';
                $content = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:' . $color . '">' . ($data['customSvg'] ?? '<svg viewBox="0 0 24 24" fill="currentColor" width="100%" height="100%"><circle cx="12" cy="12" r="10"/></svg>') . '</div>';
                break;

            default:
                // Generic: just render as colored div
                break;
        }

        $visible = ($el['visible'] ?? true) ? '' : 'display:none;';

        return '<div class="mag-el mag-el--' . $type . '" style="' . $style . $visible . '">' . $content . '</div>';
    }
}
