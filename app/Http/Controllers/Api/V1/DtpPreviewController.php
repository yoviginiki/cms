<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\Blocks\BlockStyle;
use Illuminate\Http\Response;

class DtpPreviewController extends Controller
{
    /**
     * Render DTP magazine using the full magazine viewer (magazine.blade.php).
     * Converts DTP frames (pixel positions) to viewer format (percentage positions).
     */
    public function preview(Site $site, MagazineIssue $issue): Response
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $pages = MagazineDtpPage::where('issue_id', $issue->id)->orderBy('page_index')->get();
        $frames = MagazineFrame::where('issue_id', $issue->id)->where('visible', true)->orderBy('z_index')->get();

        if ($pages->isEmpty()) {
            return response()->view('dtp-preview', [
                'issue' => ['title' => $issue->title],
                'spreads' => [],
                'pageCount' => 0,
                'frameCount' => 0,
                'layoutMode' => 'single',
                'coverMode' => 'standalone',
            ]);
        }

        $firstPage = $pages->first();
        $pageW = $firstPage->width ?? 595;
        $pageH = $firstPage->height ?? 842;

        // Issue settings
        $issueSettings = $issue->layout_final['issueSettings'] ?? [];
        $displayMode = match ($issueSettings['layoutMode'] ?? 'single') {
            'book' => 'spread',
            'presentation' => 'single',
            default => 'scroll',
        };

        // Build magazine object for the viewer
        $magazine = (object) [
            'title' => $issue->title ?? 'DTP Issue',
            'description' => $issue->subtitle ?? '',
            'cover_image' => null,
            'page_width' => $pageW,
            'page_height' => $pageH,
            'settings' => [
                'display_mode' => $displayMode,
                'view_mode' => 'full',
                'bg_color' => '#0a0a0a',
                'ui_theme' => 'dark',
                'page_transition' => 'turn',
                'transition_speed' => 500,
                'show_thumbnails' => true,
                'show_page_numbers' => true,
                'show_header' => true,
                'show_controls' => true,
                'show_toc' => true,
                'auto_hide_ui' => true,
                'pn_position' => 'bottom',
                'pn_align' => 'outer',
                'pn_size' => '9px',
            ],
        ];

        // Convert DTP frames to viewer format (percentage-based)
        $pagesJson = $pages->map(function ($page) use ($frames) {
            $pw = $page->width ?? 595;
            $ph = $page->height ?? 842;
            $bg = $page->background ?? [];

            $pageFrames = $frames->where('page_id', $page->id)->sortBy('z_index');

            $elements = $pageFrames->map(function ($frame) use ($pw, $ph) {
                $content = is_array($frame->content) ? $frame->content : [];
                $metadata = is_array($frame->metadata) ? $frame->metadata : [];
                $frameType = is_string($frame->frame_type) ? $frame->frame_type : ($frame->frame_type->value ?? 'text');

                // Convert pixel positions to percentages
                $xPct = ($frame->x / $pw) * 100;
                $yPct = ($frame->y / $ph) * 100;
                $wPct = ($frame->width / $pw) * 100;
                $hPct = ($frame->height / $ph) * 100;

                $viewerContent = [];
                $viewerType = 'text';

                if (in_array($frameType, ['text', 'quote'])) {
                    $viewerType = 'text';
                    $typo = $metadata['_typography'] ?? [];

                    // Build inline styles
                    $style = '';
                    if (!empty($typo['fontFamily'])) $style .= "font-family:{$typo['fontFamily']};";
                    if (!empty($typo['fontSize'])) $style .= "font-size:{$typo['fontSize']}pt;";
                    if (!empty($typo['fontWeight'])) $style .= "font-weight:{$typo['fontWeight']};";
                    if (!empty($typo['lineHeight'])) $style .= "line-height:{$typo['lineHeight']};";
                    if (!empty($typo['textAlign'])) $style .= "text-align:{$typo['textAlign']};";
                    if (!empty($typo['textColor'])) $style .= "color:{$typo['textColor']};";
                    if (!empty($typo['letterSpacing'])) $style .= "letter-spacing:{$typo['letterSpacing']}em;";
                    if (!empty($typo['textTransform'])) $style .= "text-transform:{$typo['textTransform']};";

                    $cols = $content['columnsInFrame'] ?? 1;
                    if ($cols > 1) $style .= "column-count:{$cols};column-gap:" . ($content['columnGap'] ?? 12) . "pt;";

                    $inset = $content['textInset'] ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
                    $style .= "padding:{$inset['top']}pt {$inset['right']}pt {$inset['bottom']}pt {$inset['left']}pt;";
                    $style .= "overflow:hidden;width:100%;height:100%;";

                    $html = $content['html'] ?? '';
                    $viewerContent['html'] = '<div style="' . e($style) . '">' . $html . '</div>';

                } elseif ($frameType === 'image') {
                    $viewerType = 'image';
                    $src = $content['src'] ?? '';
                    // Convert API asset URLs to public serve URLs
                    if (is_string($src) && preg_match('#^/api/v1/sites/([^/]+)/assets/([^/]+)/serve#', $src, $m)) {
                        $src = "/media/{$m[1]}/{$m[2]}";
                    }
                    $scheme = is_string($src) ? strtolower((string) parse_url($src, PHP_URL_SCHEME)) : '';
                    $viewerContent['src'] = ($src && (in_array($scheme, ['http', 'https']) || str_starts_with($src, '/'))) ? $src : '';
                    $viewerContent['alt'] = $content['alt'] ?? '';
                    $viewerContent['objectFit'] = $content['fitMode'] ?? 'cover';

                } elseif ($frameType === 'shape') {
                    $viewerType = 'shape';
                    $fillColor = BlockStyle::safeColor($content['fillColor'] ?? '#e5e7eb') ?: '#e5e7eb';
                    $viewerContent['fill'] = $fillColor;

                } elseif ($frameType === 'decorative') {
                    $viewerType = 'shape';
                    $viewerContent['fill'] = BlockStyle::safeColor($content['strokeColor'] ?? '#ccc') ?: '#ccc';

                } else {
                    $viewerType = 'text';
                    $viewerContent['html'] = '';
                }

                return [
                    'type' => $viewerType,
                    'content' => $viewerContent,
                    'x' => round($xPct, 2),
                    'y' => round($yPct, 2),
                    'width' => round($wPct, 2),
                    'height' => round($hPct, 2),
                    'rotation' => (float) $frame->rotation,
                    'z_index' => $frame->z_index ?? 0,
                    'style' => is_array($frame->style) ? $frame->style : [],
                ];
            })->values();

            return [
                'id' => $page->id,
                'title' => 'Page ' . ($page->page_index + 1),
                'background_color' => BlockStyle::safeColor($bg['color'] ?? '#ffffff') ?: '#ffffff',
                'background_image' => null,
                'elements' => $elements,
            ];
        })->toJson();

        return response()->view('magazine', [
            'magazine' => $magazine,
            'site' => $site,
            'pagesJson' => $pagesJson,
        ]);
    }
}
