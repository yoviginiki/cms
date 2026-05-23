<?php

namespace App\Http\Controllers;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Models\Magazine;
use App\Models\Page;
use App\Models\Site;
use App\Support\Blocks\BlockStyle;
use Illuminate\Support\Facades\DB;

class MagazineViewController extends Controller
{
    public function show(string $slug)
    {
        $site = $this->resolveSite();

        $magazine = Magazine::where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $magazine->load(['pages' => fn($q) => $q->orderBy('sort_order'), 'pages.elements' => fn($q) => $q->orderBy('z_index')]);

        // Merge global site settings into magazine settings
        $ss = $site->settings ?? [];
        $magSettings = $magazine->settings ?? [];
        $magazine->settings = array_merge($magSettings, [
            'page_transition' => $ss['mag_transition'] ?? ($magSettings['page_transition'] ?? 'slide'),
            'transition_speed' => (int) ($ss['mag_speed'] ?? ($magSettings['transition_speed'] ?? 400)),
            'spread_mode' => $ss['mag_spread'] ?? ($magSettings['spread_mode'] ?? 'auto'),
            'bg_color' => $ss['mag_bg'] ?? ($magSettings['bg_color'] ?? '#0a0a0a'),
            'show_page_numbers' => ($ss['mag_page_numbers'] ?? true) !== false,
            'pn_position' => $ss['mag_pn_position'] ?? 'bottom',
            'pn_align' => $ss['mag_pn_align'] ?? 'outer',
            'pn_size' => ($ss['mag_pn_size'] ?? '9') . 'px',
        ]);

        return view('magazine', [
            'magazine' => $magazine,
            'site' => $site,
            'pagesJson' => $magazine->pages->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'background_color' => $p->background_color,
                'background_image' => $p->background_image,
                'elements' => $p->elements->map(fn($e) => [
                    'type' => $e->type,
                    'content' => $e->content,
                    'x' => (float)$e->x,
                    'y' => (float)$e->y,
                    'width' => (float)$e->width,
                    'height' => (float)$e->height,
                    'rotation' => (float)$e->rotation,
                    'z_index' => $e->z_index,
                    'style' => $e->style,
                ]),
            ])->toJson(),
        ]);
    }

    /**
     * Show a magazine page (from Issue Composer / mag_pages system) as a flipbook.
     */
    public function showPage(string $slug)
    {
        $site = $this->resolveSite();

        $page = Page::where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('editor_mode', 'magazine')
            ->firstOrFail();

        $magPages = MagPage::where('page_id', $page->id)->orderBy('page_number')->get();
        $magElements = MagElement::where('page_id', $page->id)->orderBy('page_number')->orderBy('z_index')->get();

        // Build magazine object — per-magazine settings from page's seo_meta
        $firstPage = $magPages->first();
        $pageW = $firstPage ? ($firstPage->page_size['width'] ?? 595) : 595;
        $pageH = $firstPage ? ($firstPage->page_size['height'] ?? 842) : 842;

        $m = $page->seo_meta ?? [];

        $magazine = (object) [
            'title' => $page->title,
            'description' => '',
            'cover_image' => null,
            'page_width' => $pageW,
            'page_height' => $pageH,
            'settings' => [
                'display_mode' => $m['viewer_display_mode'] ?? 'spread',
                'view_mode' => 'full',
                'bg_color' => $m['viewer_bg'] ?? '#0a0a0a',
                'ui_theme' => 'dark',
                'page_transition' => $m['viewer_transition'] ?? 'turn',
                'transition_speed' => (int) ($m['viewer_speed'] ?? 500),
                'show_thumbnails' => true,
                'show_page_numbers' => ($m['viewer_pn'] ?? true) !== false,
                'show_header' => true,
                'show_controls' => true,
                'pn_position' => $m['viewer_pn_pos'] ?? 'bottom',
                'pn_align' => $m['viewer_pn_align'] ?? 'outer',
                'pn_size' => ($m['viewer_pn_size'] ?? '9') . 'px',
            ],
        ];

        // Convert mag_elements to the format the flipbook blade expects
        // The blade uses: x/y as percentages (0-100), content as flat object
        $pagesJson = $magPages->map(function ($mp) use ($magElements) {
            $pageW = $mp->page_size['width'] ?? 595;
            $pageH = $mp->page_size['height'] ?? 842;

            $elements = $magElements->where('page_number', $mp->page_number)->map(function ($el) use ($pageW, $pageH) {
                $data = $el->data ?? [];
                $typo = $el->typography ?? [];

                // Convert point positions to percentages
                $xPct = ($el->x / $pageW) * 100;
                $yPct = ($el->y / $pageH) * 100;
                $wPct = ($el->width / $pageW) * 100;
                $hPct = ($el->height / $pageH) * 100;

                // Build content object based on type
                $content = [];
                $type = 'text'; // default for blade

                if (in_array($el->type, ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'])) {
                    $type = 'text';
                    // Build inline styles for the text
                    $style = '';
                    if (!empty($typo['fontFamily'])) $style .= "font-family:{$typo['fontFamily']};";
                    if (!empty($typo['fontSize'])) $style .= "font-size:{$typo['fontSize']}pt;";
                    if (!empty($typo['fontWeight'])) $style .= "font-weight:{$typo['fontWeight']};";
                    if (!empty($typo['lineHeight'])) $style .= "line-height:{$typo['lineHeight']};";
                    if (!empty($typo['textAlign'])) $style .= "text-align:{$typo['textAlign']};";
                    if (!empty($typo['textColor'])) $style .= "color:{$typo['textColor']};";
                    if (!empty($typo['letterSpacing'])) $style .= "letter-spacing:{$typo['letterSpacing']}em;";
                    if (!empty($typo['textTransform'])) $style .= "text-transform:{$typo['textTransform']};";

                    $cols = $data['columnsInFrame'] ?? 1;
                    if ($cols > 1) $style .= "column-count:{$cols};column-gap:" . ($data['columnGap'] ?? 12) . "pt;";

                    $inset = $data['textInset'] ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
                    $style .= "padding:{$inset['top']}pt {$inset['right']}pt {$inset['bottom']}pt {$inset['left']}pt;";
                    $style .= "overflow:hidden;width:100%;height:100%;";

                    $content['html'] = '<div style="' . $style . '">' . ($data['content'] ?? '') . '</div>';

                } elseif (in_array($el->type, ['image_frame', 'circular_image', 'fullbleed_image'])) {
                    $type = 'image';
                    $content['src'] = $data['src'] ?? '';
                    $content['alt'] = $data['alt'] ?? '';
                    $content['objectFit'] = $data['fit'] ?? 'cover';

                } elseif ($el->type === 'rectangle') {
                    $type = 'shape';
                    $fillColor = $data['fillColor'] ?? ($el->style['fill']['color'] ?? '#ccc');
                    $content['html'] = '<div style="width:100%;height:100%;background:' . $fillColor . ';"></div>';

                } elseif ($el->type === 'video_frame') {
                    $type = 'video';
                    $url = $data['url'] ?? '';
                    preg_match('/(?:v=|\/)([\w-]{11})/', $url, $m);
                    $content['videoId'] = $m[1] ?? '';

                } else {
                    $type = 'text';
                    $content['html'] = '';
                }

                return [
                    'type' => $type,
                    'content' => $content,
                    'x' => round($xPct, 2),
                    'y' => round($yPct, 2),
                    'width' => round($wPct, 2),
                    'height' => round($hPct, 2),
                    'rotation' => (float) $el->rotation,
                    'z_index' => $el->z_index ?? 0,
                    'style' => $el->style,
                ];
            })->values();

            return [
                'id' => $mp->id,
                'title' => 'Page ' . $mp->page_number,
                'background_color' => $mp->background_color ?? '#ffffff',
                'background_image' => null,
                'elements' => $elements,
            ];
        })->toJson();

        return view('magazine', [
            'magazine' => $magazine,
            'site' => $site,
            'pagesJson' => $pagesJson,
        ]);
    }

    /**
     * Public viewer for DTP magazine issues.
     * Uses server-rendered HTML (dtp-preview) for reliable display.
     */
    public function showDtpIssue(string $issueId)
    {
        // Validate UUID format
        if (!preg_match('/^[0-9a-f\-]{36}$/', $issueId)) {
            abort(404);
        }
        // Set tenant context first (RLS requires it before any query)
        $site = $this->resolveSite();

        $issue = MagazineIssue::where('id', $issueId)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->firstOrFail();

        // Use DtpRenderService for server-side HTML rendering
        $renderService = app(\App\Domain\Magazine\Services\DtpRenderService::class);
        $data = $renderService->render($issue);

        if (empty($data['spreads'])) {
            abort(404, 'No DTP content.');
        }

        // Convert API asset URLs to public serve URLs in rendered HTML
        $spreads = json_decode(json_encode($data['spreads']), true);
        array_walk_recursive($spreads, function (&$value) {
            if (is_string($value) && preg_match('#/api/v1/sites/([^/]+)/assets/([^/]+)/serve#', $value, $m)) {
                $value = str_replace($m[0], "https://sys.ensodo.eu/media/{$m[1]}/{$m[2]}", $value);
            }
        });

        return response()->view('dtp-preview', [
            'issue' => $data['issue'],
            'spreads' => $spreads,
            'pageCount' => $data['pageCount'],
            'frameCount' => $data['frameCount'],
            'layoutMode' => $data['layoutMode'] ?? 'single',
            'coverMode' => $data['coverMode'] ?? 'standalone',
        ]);
    }

    public function index()
    {
        $site = $this->resolveSite();

        $magazines = Magazine::where('site_id', $site->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->get();

        // Also include published DTP issues
        $dtpIssues = MagazineIssue::where('site_id', $site->id)
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get();

        return view('magazine-index', [
            'magazines' => $magazines,
            'dtpIssues' => $dtpIssues,
            'site' => $site,
        ]);
    }

    private function resolveSite(): Site
    {
        // Tenants table has no RLS — get tenant ID first, then set context
        $tenant = DB::selectOne("SELECT id FROM tenants LIMIT 1");

        if (!$tenant) {
            abort(404, 'No tenant found.');
        }

        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
        DB::statement("SET app.current_tenant_id = '{$tenantId}'");

        // Now RLS is satisfied — resolve site by domain
        $host = request()->getHost();
        $originalHost = request()->header('X-Original-Host', $host);

        $site = Site::where('custom_domain', $originalHost)
            ->orWhere('custom_domain', $host)
            ->first();

        if (!$site) {
            abort(404, 'No site found for this domain.');
        }

        return $site;
    }
}
