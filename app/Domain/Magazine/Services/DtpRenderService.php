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
        // S8: run the shared HTMLPurifier magazine profile FIRST (kills event
        // handlers, javascript: URLs, unknown tags; keeps the editor's b/i and
        // the flow engine's inline margin resets); the regex below then
        // re-filters attributes for the DTP-specific style allowance
        $html = app(\App\Domain\Publishing\Services\SanitizationService::class)->purifyMagazine($html);
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
                // Allow style — only safe CSS properties via allowlist
                if (preg_match('/style\s*=\s*"([^"]*)"/', $attrs, $sm)) {
                    $css = $sm[1];
                    $safeCss = [];
                    $allowed = ['font-family','font-size','font-weight','font-style','font-variant',
                        'line-height','letter-spacing','word-spacing','text-align','text-transform',
                        'text-decoration','text-indent','vertical-align','white-space',
                        'color','background-color','background','opacity',
                        'margin','margin-top','margin-bottom','margin-left','margin-right',
                        'padding','padding-top','padding-bottom','padding-left','padding-right',
                        'border','border-radius','border-width','border-style','border-color',
                        'float','width','height','max-width','display',
                        'column-count','column-gap','column-fill','overflow'];
                    foreach (explode(';', $css) as $decl) {
                        $decl = trim($decl);
                        if (!$decl) continue;
                        $parts = explode(':', $decl, 2);
                        if (count($parts) !== 2) continue;
                        $prop = strtolower(trim($parts[0]));
                        $val = trim($parts[1]);
                        // Block dangerous values
                        if (preg_match('/expression|url\s*\(|javascript:|data:/i', $val)) continue;
                        if (in_array($prop, $allowed)) $safeCss[] = "$prop:$val";
                    }
                    if ($safeCss) {
                        $safe .= ' style="' . e(implode(';', $safeCss)) . '"';
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
            'fontsUrl' => $this->buildFontsUrl($frames),
        ];
    }

    /**
     * Google Fonts URL for every family used by the document's typography.
     * Without webfonts the published viewer falls back to system fonts and
     * within-frame line wrapping diverges from the editor (audit M-D parity).
     */
    private function buildFontsUrl($frames): ?string
    {
        $families = [];
        foreach ($frames as $frame) {
            $metadata = is_array($frame->metadata) ? $frame->metadata : [];
            $family = trim((string) ($metadata['_typography']['fontFamily'] ?? ''));
            $family = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $family);
            if ($family !== '' && !in_array(strtolower($family), ['system-ui', 'sans-serif', 'serif', 'monospace'], true)) {
                $families[$family] = true;
            }
        }
        $families['Inter'] = true; // editor default
        if (empty($families)) {
            return null;
        }
        $parts = array_map(
            fn (string $f) => 'family=' . str_replace(' ', '+', $f) . ':ital,wght@0,400;0,500;0,600;0,700;1,400',
            array_keys($families),
        );
        return 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
    }

    private function renderFrame(MagazineFrame $frame, MagazineDtpPage $page): array
    {
        $content = is_array($frame->content) ? $frame->content : [];
        $type = $frame->frame_type->value ?? (string) $frame->frame_type;

        // Build frame style — add typography/columns/padding for text frames
        $style = $this->buildFrameStyle($frame);
        $metadata = is_array($frame->metadata) ? $frame->metadata : [];
        $typo = $metadata['_typography'] ?? [];
        if (in_array($type, ['text', 'quote'])) {
            // Typography — MUST match the editor's shared style builder
            // (resources/admin/src/engine/flow/textStyle.ts). The flow engine's
            // placements are baked into slices; within-frame wrapping only
            // agrees if every width-affecting property renders identically.
            if (!empty($typo['fontFamily'])) $style .= "font-family:" . preg_replace('/[^a-zA-Z0-9\s,\-]/', '', $typo['fontFamily']) . ";";
            if (!empty($typo['fontSize'])) $style .= "font-size:" . ((float)$typo['fontSize']) . "px;";
            if (!empty($typo['fontWeight'])) $style .= "font-weight:" . ((int)$typo['fontWeight']) . ";";
            if (!empty($typo['fontStyle']) && $typo['fontStyle'] === 'italic') $style .= "font-style:italic;";
            if (!empty($typo['lineHeight'])) $style .= "line-height:" . ((float)$typo['lineHeight']) . ";";
            if (!empty($typo['textAlign'])) $style .= "text-align:" . preg_replace('/[^a-z]/', '', $typo['textAlign']) . ";";
            if (!empty($typo['textColor'])) $style .= "color:" . preg_replace('/[^a-zA-Z0-9#(),.\s]/', '', $typo['textColor']) . ";";
            if (!empty($typo['letterSpacing'])) $style .= "letter-spacing:" . ((float)$typo['letterSpacing']) . "em;";
            if (!empty($typo['textTransform']) && $typo['textTransform'] !== 'none') {
                $tt = preg_replace('/[^a-z\-]/', '', $typo['textTransform']);
                if ($tt === 'small-caps') $style .= "font-variant:small-caps;";
                elseif (in_array($tt, ['uppercase', 'lowercase', 'capitalize'], true)) $style .= "text-transform:{$tt};";
            }
            if (!empty($typo['hyphenation'])) $style .= "hyphens:auto;-webkit-hyphens:auto;";
            $style .= "overflow-wrap:break-word;word-break:break-word;";
            // Columns
            $cols = (int) ($content['columnsInFrame'] ?? 1);
            if ($cols > 1 && $cols <= 6) {
                $gap = (int) ($content['columnGap'] ?? 12);
                $style .= "column-count:{$cols};column-gap:{$gap}px;column-fill:auto;";
            }
            // Padding
            $inset = $content['textInset'] ?? null;
            if (is_array($inset)) {
                $t = (int) ($inset['top'] ?? 0); $r = (int) ($inset['right'] ?? 0);
                $b = (int) ($inset['bottom'] ?? 0); $l = (int) ($inset['left'] ?? 0);
                $style .= "padding:{$t}px {$r}px {$b}px {$l}px;";
            }
        }

        $magType = $metadata['_magType'] ?? null;
        $html = match (true) {
            $magType === 'table_frame' => $this->renderTableFrame($content),
            default => match ($type) {
            'text' => $this->renderTextFrame($content),
            'image' => $this->renderImageFrame($content),
            'quote' => $this->renderQuoteFrame($content),
            'pageNumber' => $this->renderPageNumberFrame($page, $content),
            'shape' => $this->renderShapeFrame($content),
            'line' => '<hr style="border:none;border-top:1px solid #333;margin:0;">',
            'decorative' => '<div style="width:100%;height:100%;"></div>',
            default => '<div></div>',
            },
        };

        // W1-6 vertical alignment + W1-8 drop caps — must match the editor
        if (in_array($type, ['text', 'quote'])) {
            $va = $content['verticalAlign'] ?? 'top';
            $dc = is_array($typo['dropCap'] ?? null) ? $typo['dropCap'] : [];
            $wrapClass = '';
            $dcStyleTag = '';
            if (!empty($dc['enabled'])) {
                $wrapClass = 'magdc-' . substr(str_replace('-', '', (string) $frame->id), 0, 8);
                $fs = (float) ($typo['fontSize'] ?? 14);
                $lhm = (float) ($typo['lineHeight'] ?? 1.5);
                $lines = max(2, min(8, (int) ($dc['lines'] ?? 3)));
                $size = (int) round($fs * $lhm * $lines * 0.92);
                $lh = (int) round($fs * $lhm * $lines * 0.85);
                $dcFont = preg_replace('/[^a-zA-Z0-9\s,\-]/', '', (string) ($dc['font'] ?? ''));
                $dcColor = BlockStyle::safeColor($dc['color'] ?? '') ?: '';
                $dcStyleTag = "<style>.{$wrapClass} > p:first-of-type::first-letter{float:left;font-size:{$size}px;line-height:{$lh}px;padding:0 6px 0 0;font-weight:700;"
                    . ($dcFont !== '' ? "font-family:{$dcFont};" : '')
                    . ($dcColor !== '' ? "color:{$dcColor};" : '')
                    . '}</style>';
            }
            if ($va === 'center' || $va === 'bottom' || $wrapClass !== '') {
                $justify = $va === 'bottom' ? 'flex-end' : 'center';
                $flex = ($va === 'center' || $va === 'bottom')
                    ? "height:100%;display:flex;flex-direction:column;justify-content:{$justify};"
                    : '';
                $html = $dcStyleTag . '<div' . ($wrapClass !== '' ? ' class="' . $wrapClass . '"' : '') . ($flex !== '' ? ' style="' . $flex . '"' : '') . '>' . $html . '</div>';
            }
        }

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
        // Focal point is canonically 0-1 (editor scale, audit W0-7); values
        // >1 are legacy 0-100 saves — accept both so old documents render
        $fxRaw = (float) ($content['focalPoint']['x'] ?? 0.5);
        $fyRaw = (float) ($content['focalPoint']['y'] ?? 0.5);
        $fx = max(0, min(100, (int) round($fxRaw <= 1 ? $fxRaw * 100 : $fxRaw)));
        $fy = max(0, min(100, (int) round($fyRaw <= 1 ? $fyRaw * 100 : $fyRaw)));
        $opacity = max(0, min(100, (int) ($content['opacity'] ?? 100))) / 100;

        $style = "width:100%;height:100%;object-fit:{$objectFit};object-position:{$fx}% {$fy}%;display:block;";
        if ($opacity < 1) {
            $style .= "opacity:{$opacity};";
        }

        // content mode: pan/scale/rotate inside the frame — must match the
        // editor's transform exactly (audit W1-12; fields persist since W0-6)
        $offX = (float) ($content['imageOffsetX'] ?? 0);
        $offY = (float) ($content['imageOffsetY'] ?? 0);
        $scale = (float) ($content['imageScale'] ?? 1);
        $imgRot = (float) ($content['imageRotation'] ?? 0);
        if ($offX || $offY || $scale !== 1.0 || $imgRot) {
            $offX = max(-2000, min(2000, $offX));
            $offY = max(-2000, min(2000, $offY));
            $scale = max(0.05, min(20, $scale ?: 1));
            $imgRot = max(-360, min(360, $imgRot));
            $style .= "transform:translate({$offX}px, {$offY}px) scale({$scale}) rotate({$imgRot}deg);transform-origin:center center;";
        }

        // image filters — rendered by the editor since MAG-P12, never published
        $f = is_array($content['filters'] ?? null) ? $content['filters'] : [];
        $filters = [];
        $b = (int) ($f['brightness'] ?? 100);
        $c = (int) ($f['contrast'] ?? 100);
        $sat = (int) ($f['saturation'] ?? 100);
        if ($b !== 100) $filters[] = 'brightness(' . max(0, min(300, $b)) . '%)';
        if ($c !== 100) $filters[] = 'contrast(' . max(0, min(300, $c)) . '%)';
        if ($sat !== 100) $filters[] = 'saturate(' . max(0, min(300, $sat)) . '%)';
        if (!empty($f['grayscale'])) $filters[] = 'grayscale(1)';
        if ($filters) {
            $style .= 'filter:' . implode(' ', $filters) . ';';
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

    private function renderPageNumberFrame(MagazineDtpPage $page, array $content = []): string
    {
        // W2-11: real formats (the audit found NO roman converter in the repo
        // and published page numbers ignored format/prefix/suffix entirely)
        $startAt = max(1, (int) ($content['startAt'] ?? 1));
        $n = $page->page_index + $startAt;
        $formatted = match ($content['format'] ?? 'decimal') {
            'roman-lower' => strtolower($this->toRoman($n)),
            'roman-upper' => $this->toRoman($n),
            'alpha-lower' => strtolower($this->toAlpha($n)),
            'alpha-upper' => $this->toAlpha($n),
            default => (string) $n,
        };
        $prefix = e((string) ($content['prefix'] ?? ''));
        $suffix = e((string) ($content['suffix'] ?? ''));

        return '<span style="font-size:11px;color:#666;">' . $prefix . $formatted . $suffix . '</span>';
    }

    private function toRoman(int $n): string
    {
        $table = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
            50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $out = '';
        foreach ($table as $val => $sym) {
            while ($n >= $val) {
                $out .= $sym;
                $n -= $val;
            }
        }
        return $out;
    }

    private function toAlpha(int $n): string
    {
        $out = '';
        while ($n > 0) {
            $n--;
            $out = chr(65 + ($n % 26)) . $out;
            $n = intdiv($n, 26);
        }
        return $out;
    }

    /** real <table> output (tables track) — mirrors the editor's render */
    private function renderTableFrame(array $content): string
    {
        $headers = is_array($content['tableHeaders'] ?? null) ? $content['tableHeaders'] : ['Col 1', 'Col 2'];
        $rows = is_array($content['tableRows'] ?? null) ? $content['tableRows'] : [];
        $border = BlockStyle::safeColor($content['tableBorderColor'] ?? '#e5e7eb') ?: '#e5e7eb';
        $stripes = ($content['tableStripes'] ?? true) !== false;

        $cellBase = "border:1px solid {$border};padding:4px 6px;";
        $out = '<table style="width:100%;border-collapse:collapse;font-size:11px;color:#1a1a1a;"><thead><tr>';
        foreach ($headers as $h) {
            $out .= '<th style="' . $cellBase . 'text-align:left;font-weight:600;background:#f6f5f2;">' . e((string) $h) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $ri => $row) {
            $rowStyle = $stripes && $ri % 2 === 1 ? ' style="background:#fafaf8;"' : '';
            $out .= '<tr' . $rowStyle . '>';
            foreach ($headers as $ci => $unused) {
                $cell = is_array($row) ? ($row[$ci] ?? '') : '';
                $out .= '<td style="' . $cellBase . '">' . e((string) $cell) . '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';

        return $out;
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
        // Spread images need overflow:visible to extend beyond page; all others hidden
        $spanMode = $frame->metadata['spanMode'] ?? 'page';
        $overflow = ($spanMode === 'spread') ? 'visible' : 'hidden';

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
        // Pages clip their content, EXCEPT when a spread-spanning frame needs
        // to extend across the gutter (audit defect: published spread images
        // were clipped because this parameter was ignored)
        $overflow = $hasSpreadImage ? 'visible' : 'hidden';
        return "position:relative;width:{$w}px;height:{$h}px;background:{$bg};overflow:{$overflow};";
    }
}
