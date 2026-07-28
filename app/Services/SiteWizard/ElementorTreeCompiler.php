<?php

namespace App\Services\SiteWizard;

use Illuminate\Support\Str;

/**
 * Compiles an Elementor page document (_elementor_data JSON) STRAIGHT into the
 * native block tree — no DOM heuristics. Every Elementor widget maps to the
 * closest native module (heading→heading, counter→stats, image-carousel→
 * logostrip/gallery, elementskit-accordion→accordion, google_maps→map, …) with
 * its real settings: section backgrounds and overlays, entrance animations
 * (_animation → __animation), hover effects, colors and alignment. The result
 * is a fully editable page that moves and reads like the source.
 *
 * Media: every image URL runs through the $importImage callback (url, alt) →
 * new url, so the caller controls media-library import and deduping.
 */
class ElementorTreeCompiler
{
    private const ANIM_MAP = [
        'fadein' => 'fade', 'fadeinup' => 'slide-up', 'fadeindown' => 'slide-down',
        'fadeinleft' => 'slide-left', 'fadeinright' => 'slide-right',
        'slideinup' => 'slide-up', 'slideindown' => 'slide-down',
        'slideinleft' => 'slide-left', 'slideinright' => 'slide-right',
        'zoomin' => 'zoom', 'bouncein' => 'scale-in', 'pulse' => 'scale-in',
    ];

    private const ICON_MAP = [
        'snowflake' => 'Snowflake', 'cog' => 'Settings', 'gear' => 'Settings', 'wrench' => 'Wrench',
        'truck' => 'Truck', 'phone' => 'Phone', 'envelope' => 'Mail', 'map' => 'MapPin',
        'check' => 'CheckCircle', 'star' => 'Star', 'bolt' => 'Zap', 'shield' => 'Shield',
        'users' => 'Users', 'user' => 'User', 'clock' => 'Clock', 'headset' => 'Headphones',
        'tools' => 'Wrench', 'thermometer' => 'Thermometer', 'industry' => 'Factory',
        'box' => 'Package', 'boxes' => 'Boxes', 'award' => 'Award', 'handshake' => 'Handshake',
    ];

    /** @var callable(string,string):string */
    private $importImage;
    private int $order = 0;
    /** @var array<string,string> Elementor kit global color id => hex */
    private array $globalColors = [];
    /** @var array{projects?:array,categories?:array} source-site context for dynamic widgets */
    private array $context = [];

    /**
     * @param array $document decoded _elementor_data
     * @param callable(string,string):string $importImage url,alt → serve url
     * @return array<int,array> section nodes for BlockService::syncBlocks
     */
    public function compile(array $document, callable $importImage, array $globalColors = [], array $context = []): array
    {
        $this->importImage = $importImage;
        $this->globalColors = $globalColors;
        $this->context = $context;
        $this->order = 0;

        $sections = [];
        foreach ($document as $i => $top) {
            $section = $i === 0 ? $this->heroSection($top) : null;
            $section ??= $this->section($top);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * The page's FIRST section, when it is a classic hero (background photo +
     * headline), becomes a real hero module: title, intro, CTA and the photo
     * with a readability overlay — instead of loose text flattened over the
     * image. Returns null when the opening section isn't hero-shaped.
     */
    private function heroSection(array $el): ?array
    {
        $s = $el['settings'] ?? [];
        $bgImage = $this->imageUrl($s['background_image'] ?? null);
        if (($s['background_background'] ?? '') !== 'classic' || $bgImage === null) {
            return null;
        }

        // Collect the hero's cast: headline, intro, CTA, video, checklist, badge.
        // The second heading (after the main title) is the video's label.
        $found = ['heading' => null, 'text' => null, 'button' => null, 'video' => null, 'videoLabel' => null, 'list' => null, 'badge' => null];
        $scan = function (array $node) use (&$scan, &$found) {
            foreach ($node['elements'] ?? [] as $child) {
                if (($child['elType'] ?? '') === 'widget') {
                    $type = $child['widgetType'] ?? '';
                    $slot = match ($type) {
                        'heading' => $found['heading'] === null ? 'heading' : ($found['videoLabel'] === null ? 'videoLabel' : null),
                        'text-editor' => $found['text'] === null ? 'text' : null,
                        'elementskit-creative-button' => $found['button'] === null ? 'button' : null,
                        'elementskit-video' => $found['video'] === null ? 'video' : null,
                        'icon-list' => $found['list'] === null ? 'list' : null,
                        'image-box' => $found['badge'] === null ? 'badge' : null,
                        default => null,
                    };
                    if ($slot !== null) {
                        $found[$slot] = $child['settings'] ?? [];
                    }
                } else {
                    $scan($child);
                }
            }
        };
        $scan($el);
        if ($found['heading'] === null) {
            return null;
        }

        // The source hero is a layered slider composition — rebuild it as the
        // native slider: background photo (subtle Ken Burns motion), the
        // source's navy gradient, and each text as an absolutely-positioned
        // layer entering on its own animation beat.
        $bg = ($this->importImage)($bgImage, '');
        $navy = $this->color($this->globalColors['primary'] ?? null) ?? '#1a202c';
        $overlay = 'linear-gradient(270deg, rgba(26,32,44,0) 11%, rgba(26,32,44,0.8) 61%)';

        $layer = function (string $type, array $data, array $layout, string $preset, float $delay) {
            $data['layout'] = $layout + ['zIndex' => 3];
            $data['animation'] = ['in' => ['preset' => $preset, 'delay' => $delay, 'duration' => 1.0]];

            return [
                'id' => (string) Str::uuid(), 'type' => $type, 'level' => 'module', 'order' => 0,
                'data' => $data, 'children' => [],
            ];
        };

        // Hero headline as a per-letter reveal (matches the source's split-text
        // animation). Emitted as an un-animated slide layer so its own CSS
        // stagger drives the entrance, not the slider's layer fade.
        $hTypo = $this->headingTypography($found['heading']);
        $hStyle = 'margin:0;font-family:var(--font-heading,inherit);color:#fff;font-weight:700;'
            . 'font-size:' . ($hTypo['fontSize'] ?? 'var(--font-size-3xl,4rem)') . ';'
            . 'line-height:' . ($hTypo['lineHeight'] ?? '1.2') . ';'
            . (isset($hTypo['letterSpacing']) ? 'letter-spacing:' . $hTypo['letterSpacing'] . ';' : '');
        $layers = [[
            'id' => (string) Str::uuid(), 'type' => 'html-embed', 'level' => 'module', 'order' => 0,
            'data' => [
                'html' => $this->splitLettersHtml($this->plain($found['heading']['title'] ?? ''), $hStyle),
                'layout' => ['x' => '6%', 'y' => '22%', 'widthPct' => 60, 'zIndex' => 3],
            ],
            'children' => [],
        ]];
        if ($found['text'] !== null) {
            $layers[] = $layer('text', [
                'content' => $this->heroIntro($found['text']['editor'] ?? ''),
                'textColor' => '#ffffff',
            ], ['x' => '6%', 'y' => '44%', 'widthPct' => 46], 'fadeUp', 0.55);
        }
        if ($found['button'] !== null) {
            $btn = array_filter([
                'text' => $this->plain($found['button']['ekit_btn_text'] ?? ''), 'style' => 'primary',
            ] + $this->buttonSkin($found['button']), fn ($v) => $v !== null && $v !== '');
            if (($u = $this->url($found['button']['ekit_btn_url'] ?? null)) !== null) {
                $btn['url'] = $u;
            }
            $layers[] = $layer('button', $btn, ['x' => '6%', 'y' => '67%'], 'fadeUp', 0.9);
        }
        // Video pop-out ("Заявете консултация" + play button), beside the CTA.
        if ($found['video'] !== null) {
            $vUrl = $this->url($found['video']['ekit_video_popup_url'] ?? null);
            $vLabel = $found['videoLabel'] !== null ? $this->plain($found['videoLabel']['title'] ?? '') : '';
            if ($vUrl !== null && $vLabel !== '') {
                $layers[] = $layer('html-embed', ['html' => $this->heroVideoHtml($vLabel, $vUrl)],
                    ['x' => '30%', 'y' => '68%'], 'fadeUp', 1.05);
            }
        }
        if ($found['list'] !== null) {
            $items = array_values(array_filter(array_map(fn ($i) => $this->plain($i['text'] ?? ''), (array) ($found['list']['icon_list'] ?? []))));
            if ($items !== []) {
                $layers[] = $layer('html-embed', ['html' => $this->checkListHtml(array_slice($items, 0, 3), '#fff')],
                    ['x' => '6%', 'y' => '80%', 'widthPct' => 30], 'slideRight', 1.2);
            }
        }
        if ($found['badge'] !== null) {
            $t = $this->plain($found['badge']['title_text'] ?? '');
            $d = $this->plain($found['badge']['description_text'] ?? '');
            if ($t !== '' || $d !== '') {
                $layers[] = $layer('text', [
                    'content' => '<p><strong>' . e($t) . '</strong>' . ($d !== '' ? '<br>' . e($d) : '') . '</p>',
                    'textColor' => '#ffffff',
                ], ['x' => '40%', 'y' => '80%', 'widthPct' => 24], 'fadeUp', 1.5);
            }
        }

        $slide = [
            'id' => (string) Str::uuid(), 'type' => 'slide', 'level' => 'module', 'order' => 0,
            'data' => [
                'background' => ['type' => 'image', 'src' => $bg, 'overlay' => $overlay, 'kenBurns' => true],
                'label' => 'Hero',
            ],
            'children' => $this->reorder($layers),
        ];
        $slider = [
            'id' => (string) Str::uuid(), 'type' => 'slider', 'level' => 'module', 'order' => 0,
            'data' => [
                'height' => ['desktop' => '860px', 'tablet' => '640px', 'mobile' => '560px'],
                'swiper' => ['effect' => 'fade', 'autoplay' => false, 'loop' => false, 'navigation' => false, 'pagination' => false],
            ],
            'children' => [$slide],
        ];

        return [
            'id' => (string) Str::uuid(), 'type' => 'section', 'level' => 'section',
            'order' => $this->order++,
            'data' => ['padding_top' => '0px', 'padding_bottom' => '0px', 'max_width' => '1200px', 'width_mode' => 'full'],
            'children' => $this->reorder([
                $this->row('1', [$this->column([$slider])]),
            ]),
        ];
    }

    /** Hero intro: keep the source's paragraph structure; cap runaway length. */
    private function heroIntro(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (mb_strlen(strip_tags($html)) > 500) {
            return '<p>' . e(mb_substr($this->plain($html), 0, 300)) . '…</p>';
        }

        return $html;
    }

    /** A checklist rendered as raw HTML with blue fa-check-circle icons and a
     * caller-chosen text colour (white on the dark hero, muted on light bands). */
    private function checkListHtml(array $items, string $textColor): string
    {
        $icon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="' . $this->accent() . '" style="flex-shrink:0">'
            . '<path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-1.2 14.6l-4.2-4.2 1.5-1.5 2.7 2.7 5.4-5.4 1.5 1.5-6.9 6.9z"/></svg>';
        $rows = '';
        foreach ($items as $t) {
            $rows .= '<div style="display:flex;align-items:center;gap:10px;margin:0 0 12px;color:' . $textColor . ';'
                . 'font-size:16px;line-height:1.3">' . $icon . '<span>' . e($t) . '</span></div>';
        }

        return '<div>' . $rows . '</div>';
    }

    /** Icon-box as raw HTML. Position 'left' → the SVG icon in a rounded blue
     * tile beside a compact title (feature row, as in the "about" band); any
     * other position → a plain brand-blue icon above the title (as in the
     * service tiles). Both keep title/description compact, not a giant heading. */
    private function featureBoxHtml(string $iconUrl, string $title, string $desc, string $position, ?string $boxBg = null): string
    {
        $blue = 'filter:brightness(0) saturate(100%) invert(34%) sepia(93%) saturate(1500%) hue-rotate(205deg) brightness(96%) contrast(94%)';
        $descHtml = $desc !== ''
            ? '<div style="color:var(--color-text-muted,#64748b);margin-top:4px;font-size:15px">' . e($desc) . '</div>'
            : '';

        // Icon-box with its own solid box background → a real card (icon-top).
        // Literal colours keep the card's text readable even on a dark band
        // (they're immune to the dark-section lightenText swap).
        if ($boxBg !== null) {
            return '<div style="background:' . $boxBg . ';padding:28px;border-radius:20px;border:1px solid rgba(26,32,44,0.08);margin:0 0 16px">'
                . '<img src="' . e($iconUrl) . '" alt="" width="40" height="40" style="' . $blue . ';margin-bottom:14px">'
                . '<div style="font-weight:700;font-size:var(--font-size-xl,1.25rem);line-height:1.35;color:#1a202c">' . e($title) . '</div>'
                . ($desc !== '' ? '<div style="color:#6d6d6d;margin-top:4px;font-size:15px">' . e($desc) . '</div>' : '')
                . '</div>';
        }

        if ($position === 'left') {
            $tile = '<span style="flex-shrink:0;width:60px;height:60px;border-radius:14px;background:' . $this->accent() . ';'
                . 'display:inline-flex;align-items:center;justify-content:center">'
                . '<img src="' . e($iconUrl) . '" alt="" width="30" height="30" style="filter:brightness(0) invert(1)"></span>';
            $body = '<div><div style="font-weight:700;font-size:var(--font-size-xl,1.25rem);line-height:1.35;color:var(--color-heading,#0f172a)">'
                . e($title) . '</div>' . $descHtml . '</div>';

            return '<div style="display:flex;align-items:flex-start;gap:16px;margin:0 0 20px">' . $tile . $body . '</div>';
        }

        // Icon-top (service cards): padded header that, on hover, fills with the
        // accent from the bottom while the icon darkens and the title goes white
        // — matching the source's hover_from_bottom overlay.
        $uid = 'sf' . substr(md5($iconUrl . $title), 0, 7);
        $accent = $this->accent();

        return '<div class="' . $uid . '" style="position:relative;padding:26px 24px 20px;border-radius:14px;'
            . 'background:linear-gradient(to top,' . $accent . ',' . $accent . ') no-repeat bottom / 100% 0;transition:background-size .4s ease">'
            . '<img src="' . e($iconUrl) . '" alt="" width="40" height="40" class="ic" style="' . $blue . ';margin-bottom:14px;transition:filter .4s">'
            . '<div class="ti" style="font-weight:700;font-size:var(--font-size-xl,1.25rem);line-height:1.35;color:var(--color-heading,#0f172a);transition:color .4s">' . e($title) . '</div>'
            . $descHtml
            // Trigger the hover from the WHOLE card (the column that wraps this
            // header + the image below), not just the header box.
            . '<style>.column-block:has(.' . $uid . '):hover .' . $uid . '{background-size:100% 100%!important}'
            . '.column-block:has(.' . $uid . '):hover .ic{filter:brightness(0) invert(.1)!important}'
            . '.column-block:has(.' . $uid . '):hover .ti{color:#fff!important}'
            . '@media(prefers-reduced-motion:reduce){.' . $uid . '{transition:none}}</style></div>';
    }

    /** Headline HTML with each letter in its own span on a staggered entrance;
     * words stay unbreakable so wrapping still happens at spaces. */
    private function splitLettersHtml(string $text, string $hStyle): string
    {
        $text = trim($text);
        $i = 0;
        $out = '';
        foreach (preg_split('/\s+/u', $text) as $word) {
            if ($word === '') {
                continue;
            }
            $letters = '';
            foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $delay = min(1400, $i * 30);
                $letters .= '<span style="display:inline-block;opacity:0;animation:heroLtr .55s cubic-bezier(.2,.7,.3,1) ' . $delay . 'ms forwards">' . e($ch) . '</span>';
                $i++;
            }
            $out .= '<span style="display:inline-block;white-space:nowrap">' . $letters . '</span> ';
            $i++;
        }

        return '<h1 class="hero-split" style="' . $hStyle . '">' . trim($out) . '</h1>'
            . '<style>@keyframes heroLtr{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}'
            . '@media(prefers-reduced-motion:reduce){.hero-split span{opacity:1!important;transform:none!important;animation:none!important}}</style>';
    }

    /** Elementor text-path → a slowly-rotating circular text badge with a
     * centered arrow (the source's "Свържете се с нас" spinner). */
    private function circularTextHtml(string $text): string
    {
        $text = trim($text) !== '' ? $text : 'Свържете се с нас • ';
        $uid = 'cp' . substr(md5($text), 0, 6);
        $accent = $this->accent();

        return '<div style="position:relative;width:150px;height:150px;margin:0 0 28px">'
            . '<svg viewBox="0 0 200 200" width="150" height="150" style="animation:' . $uid . ' 16s linear infinite">'
            . '<defs><path id="' . $uid . 'p" d="M100,100 m-74,0 a74,74 0 1,1 148,0 a74,74 0 1,1 -148,0" fill="none"/></defs>'
            . '<text fill="' . $accent . '" font-size="12.5" font-weight="700" letter-spacing="1.5">'
            . '<textPath href="#' . $uid . 'p">' . e($text) . '</textPath></text></svg>'
            . '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:46px;height:46px;'
            . 'border-radius:50%;background:' . $accent . ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;line-height:1">↗</span>'
            . '<style>@keyframes ' . $uid . '{to{transform:rotate(360deg)}}'
            . '@media(prefers-reduced-motion:reduce){[style*="' . $uid . '"]{animation:none!important}}</style></div>';
    }

    /** Hero "request a consultation" label + circular play button (html-embed
     * layer) linking to the source's popup video. */
    private function heroVideoHtml(string $label, string $url): string
    {
        $play = '<span style="display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;'
            . 'border-radius:50%;background:rgba(173,213,247,0.92);flex-shrink:0">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="#0b3382"><path d="M8 5v14l11-7z"/></svg></span>';

        return '<a href="' . e($url) . '" target="_blank" rel="noopener" '
            . 'style="display:inline-flex;align-items:center;gap:16px;text-decoration:none">'
            . '<span style="font-weight:700;color:#ffffff;font-size:16px;white-space:nowrap">' . e($label) . '</span>'
            . $play . '</a>';
    }

    /** Source button colours/size (ElementsKit creative button) → button-block
     * skin fields, so the CTA keeps its real look instead of the theme default. */
    private function buttonSkin(array $s): array
    {
        $weight = (string) ($s['ekit_btn_typography_font_weight'] ?? '');

        return array_filter([
            'bgColor' => $this->colorGlobalFirst($s, 'ekit_btn_bg_color'),
            'textColor' => $this->colorGlobalFirst($s, 'ekit_btn_text_color'),
            'fontSize' => $this->edim($s['ekit_btn_typography_font_size'] ?? null),
            'fontWeight' => in_array($weight, ['400', '500', '600', '700', '800', '900'], true) ? $weight : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** A copy of the element tree without the given widget ids. */
    private function stripWidgets(array $el, array $ids): array
    {
        $el['elements'] = array_values(array_filter(array_map(
            fn ($c) => ($c['elType'] ?? '') === 'widget'
                ? (in_array($c['id'] ?? '', $ids, true) ? null : $c)
                : $this->stripWidgets($c, $ids),
            $el['elements'] ?? []
        )));

        return $el;
    }

    // ── structure ──

    private function section(array $el): ?array
    {
        $rows = $this->rowsOf($el);
        if ($rows === []) {
            return null;
        }

        $s = $el['settings'] ?? [];
        $pad = $s['padding'] ?? null;
        $padTop = (is_array($pad) && ($pad['unit'] ?? 'px') === 'px' && is_numeric($pad['top'] ?? null)) ? min(200, (int) $pad['top']) . 'px' : '48px';
        $padBottom = (is_array($pad) && ($pad['unit'] ?? 'px') === 'px' && is_numeric($pad['bottom'] ?? null)) ? min(200, (int) $pad['bottom']) . 'px' : '48px';
        $data = ['padding_top' => $padTop, 'padding_bottom' => $padBottom, 'max_width' => '1200px'];

        $bgImage = $this->imageUrl($s['background_image'] ?? null);
        if (($s['background_background'] ?? '') === 'classic' && $bgImage !== null) {
            $data['bg_type'] = 'image';
            $data['bg_image'] = ($this->importImage)($bgImage, '');
            $data['bg_image_size'] = 'cover';
            $overlay = $this->settingColor($s, 'background_overlay_color');
            if (($s['background_overlay_background'] ?? '') === 'classic' && $overlay !== null) {
                $data['bg_overlay_color'] = $overlay;
                $data['bg_overlay_opacity'] = min(1, max(0, (float) ($s['background_overlay_opacity']['size'] ?? 0.5)));
            }
        } elseif (($bg = $this->settingColor($s, 'background_color')) !== null) {
            $data['bg_type'] = 'color';
            $data['bg_color'] = $bg;
        }

        // Dark band: anything the source left on the default (dark) text color
        // must flip light, or it vanishes into the background.
        if (($data['bg_color'] ?? null) !== null && $this->isDark($data['bg_color'])) {
            $rows = $this->lightenText($rows);
        }

        return [
            'id' => (string) Str::uuid(), 'type' => 'section', 'level' => 'section',
            'order' => $this->order++, 'data' => $data, 'children' => $this->reorder($rows),
        ];
    }

    private function isDark(string $hex): bool
    {
        if (preg_match('/^#([0-9a-f]{6})/i', $hex, $m) !== 1) {
            return false;
        }
        [$r, $g, $b] = sscanf($m[1], '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 < 0.4;
    }

    /** Give color-less headings/text/list modules a light tone (recursive over rows). */
    private function lightenText(array $nodes): array
    {
        foreach ($nodes as $i => $node) {
            if (($node['level'] ?? '') === 'module') {
                $type = $node['type'];
                // Headings key on `color`, text blocks on `textColor`.
                if ($type === 'heading' && empty($node['data']['color'])) {
                    $nodes[$i]['data']['color'] = '#ffffff';
                } elseif ($type === 'text' && empty($node['data']['textColor'])) {
                    $nodes[$i]['data']['textColor'] = '#d5d9e2';
                } elseif ($type === 'html-embed' && !empty($node['data']['html'])) {
                    // Feature-box tiles / checklists carry inline colour tokens
                    // that read dark on light bands — swap to light on dark ones.
                    $nodes[$i]['data']['html'] = strtr($node['data']['html'], [
                        'color:var(--color-heading,#0f172a)' => 'color:#ffffff',
                        'var(--color-text-muted,#64748b)' => '#d5d9e2',
                        'var(--color-text-muted,#5b6472)' => '#d5d9e2',
                    ]);
                }
            }
            if (!empty($node['children'])) {
                $nodes[$i]['children'] = $this->lightenText($node['children']);
            }
        }

        return $nodes;
    }

    /**
     * Recursive structure mapping. An element that is itself a flex ROW of
     * 2-4 sub-containers becomes ONE multi-column row (side-by-side layout
     * preserved, real width ratios on the 12-grid). Everything else walks its
     * children in order: widget runs become single-column rows, child
     * containers recurse — so nested header rows and card rows survive.
     */
    private function rowsOf(array $el): array
    {
        $kids = $el['elements'] ?? [];
        $containerKids = array_values(array_filter($kids, fn ($e) => ($e['elType'] ?? '') === 'container'));
        $dir = $el['settings']['flex_direction'] ?? 'row';

        if ($dir !== 'column' && count($kids) >= 2 && count($containerKids) === count($kids)) {
            // Prefer the explicitly-widthed containers as the columns; any
            // width-less trailing containers become their own rows below.
            $widthed = array_values(array_filter($containerKids, function ($c) {
                $w = $c['settings']['width'] ?? null;

                return is_array($w) && ($w['unit'] ?? '') === '%' && is_numeric($w['size'] ?? null) && (float) $w['size'] <= 100;
            }));
            $cols = count($widthed) >= 2 ? $widthed : $containerKids;
            if (count($cols) <= 4) {
                $colIds = array_map(fn ($c) => $c['id'] ?? '', $cols);
                $rows = [];
                if (($row = $this->columnsRow($cols)) !== null) {
                    $rows[] = $row;
                }
                foreach ($containerKids as $extra) {
                    if (!in_array($extra['id'] ?? '', $colIds, true)) {
                        foreach ($this->rowsOf($extra) as $r) {
                            $rows[] = $r;
                        }
                    }
                }
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        $rows = [];
        $buffer = [];
        $flush = function () use (&$rows, &$buffer) {
            if ($buffer !== []) {
                $rows[] = $this->row('1', [$this->column($this->mergeStats($buffer))]);
                $buffer = [];
            }
        };
        foreach ($kids as $child) {
            if (($child['elType'] ?? '') === 'widget') {
                if (($child['widgetType'] ?? '') === 'coolify-project-grid') {
                    $flush();
                    if (($row = $this->projectCardsRow()) !== null) {
                        $rows[] = $row;
                    }
                    continue;
                }
                foreach ($this->widgetModules($child) as $m) {
                    $buffer[] = $m;
                }
                continue;
            }
            $flush();
            // A row container holding ONLY 2-4 widgets is also a columns row
            // (icon-box strips, button pairs) — each widget gets a column.
            $grand = $child['elements'] ?? [];
            $allWidgets = $grand !== [] && count($grand) <= 4
                && count(array_filter($grand, fn ($g) => ($g['elType'] ?? '') === 'widget')) === count($grand)
                && (($child['settings']['flex_direction'] ?? 'row') !== 'column');
            if ($allWidgets && count($grand) >= 2) {
                $columns = [];
                foreach ($grand as $g) {
                    $mods = $this->widgetModules($g);
                    if ($mods !== []) {
                        $columns[] = $this->column($mods);
                    }
                }
                if (count($columns) >= 2) {
                    $layouts = [2 => '1/2+1/2', 3 => '1/3+1/3+1/3', 4 => '1/4+1/4+1/4+1/4'];
                    $rows[] = $this->row($layouts[count($columns)] ?? '1/2+1/2', $columns);
                    continue;
                }
                foreach ($columns as $col) {
                    $rows[] = $this->row('1', [$col]);
                }
                continue;
            }
            foreach ($this->rowsOf($child) as $r) {
                $rows[] = $r;
            }
        }
        $flush();

        return array_values(array_filter($rows));
    }

    /** 2-4 containers side by side → one row; image-only columns collapse to a compact gallery. */
    private function columnsRow(array $conts): ?array
    {
        $columns = [];
        $widths = [];
        foreach ($conts as $c) {
            $modules = $this->flattenModules($c);
            if ($modules === []) {
                continue;
            }
            $images = array_filter($modules, fn ($m) => $m['type'] === 'image');
            if (count($modules) >= 2 && count($images) === count($modules)) {
                $modules = [$this->module('gallery', [
                    'images' => array_map(fn ($m) => $m['data']['url'], $modules),
                    'layout' => 'grid', 'columns' => 2,
                    'effects' => ['enabled' => true, 'hover' => ['enabled' => true, 'preset' => 'shine']],
                    '__animation' => ['entrance' => 'zoom', 'duration' => 700],
                ])];
            }
            $columns[] = $this->column($modules, $this->cardStyle($c));
            $w = $c['settings']['width'] ?? null;
            $widths[] = (is_array($w) && ($w['unit'] ?? '') === '%' && is_numeric($w['size'] ?? null) && (float) $w['size'] <= 100)
                ? (float) $w['size'] : null;
        }
        if ($columns === []) {
            return null;
        }
        if (count($columns) === 1) {
            return $this->row('1', $columns);
        }
        $layouts = [2 => '1/2+1/2', 3 => '1/3+1/3+1/3', 4 => '1/4+1/4+1/4+1/4'];
        $row = $this->row($layouts[count($columns)] ?? '1/2+1/2', array_slice($columns, 0, 4));
        if (!in_array(null, $widths, true) && count($widths) === count($columns)) {
            $spans = array_map(fn ($w) => max(2, min(10, (int) round($w * 12 / 100))), $widths);
            while (array_sum($spans) > 12) { $spans[array_search(max($spans), $spans, true)]--; }
            while (array_sum($spans) < 12) { $spans[array_search(min($spans), $spans, true)]++; }
            $row['data']['col_spans'] = $spans;
        }

        return $row;
    }

    /** All modules inside a container, order preserved, nesting flattened. */
    private function flattenModules(array $el): array
    {
        $modules = [];
        foreach ($el['elements'] ?? [] as $child) {
            if (($child['elType'] ?? '') === 'widget') {
                foreach ($this->widgetModules($child) as $m) {
                    $modules[] = $m;
                }
            } else {
                foreach ($this->flattenModules($child) as $m) {
                    $modules[] = $m;
                }
            }
        }

        return $this->mergeBadgeRow($this->mergeStats($modules));
    }

    /**
     * A circular text-path badge immediately followed by card feature-boxes is,
     * in the source, one horizontal cluster (badge + cards on a line) — not a
     * vertical stack. Fuse them into a single flex-row html-embed.
     */
    private function mergeBadgeRow(array $modules): array
    {
        $isBadge = fn ($m) => ($m['type'] ?? '') === 'html-embed' && str_contains($m['data']['html'] ?? '', 'textPath');
        $isCard = fn ($m) => ($m['type'] ?? '') === 'html-embed' && str_contains($m['data']['html'] ?? '', 'padding:28px;border-radius');

        $out = [];
        $n = count($modules);
        for ($i = 0; $i < $n;) {
            if ($isBadge($modules[$i]) && isset($modules[$i + 1]) && $isCard($modules[$i + 1])) {
                $cards = '';
                $j = $i + 1;
                while ($j < $n && $isCard($modules[$j])) {
                    $cards .= '<div style="flex:1 1 180px">' . $modules[$j]['data']['html'] . '</div>';
                    $j++;
                }
                $out[] = $this->module('html-embed', ['html' =>
                    '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:18px;margin:8px 0 16px">'
                    . '<div style="flex:0 0 130px">' . $modules[$i]['data']['html'] . '</div>' . $cards . '</div>']);
                $i = $j;
                continue;
            }
            $out[] = $modules[$i];
            $i++;
        }

        return $out;
    }

    /** Consecutive single-item stats blocks collapse into one multi-column stats. */
    private function mergeStats(array $modules): array
    {
        $out = [];
        foreach ($modules as $m) {
            $last = $out !== [] ? count($out) - 1 : null;
            if ($m['type'] === 'stats' && $last !== null && $out[$last]['type'] === 'stats' && count($out[$last]['data']['items']) < 4) {
                $out[$last]['data']['items'] = array_merge($out[$last]['data']['items'], $m['data']['items']);
                $out[$last]['data']['columns'] = count($out[$last]['data']['items']);
                continue;
            }
            $out[] = $m;
        }

        return $out;
    }

    // ── widgets ──

    /** @return array<int,array> zero or more module nodes for one widget */
    private function widgetModules(array $el): array
    {
        $s = $el['settings'] ?? [];
        $shared = $this->sharedProps($s);

        $modules = match ($el['widgetType'] ?? '') {
            'heading' => [$this->module('heading', array_filter([
                'text' => $this->plain($s['title'] ?? ''),
                'level' => in_array($s['header_size'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $s['header_size'] : 'h2',
                'textAlign' => in_array($s['align'] ?? '', ['left', 'center', 'right'], true) ? $s['align'] : null,
                'color' => $this->settingColor($s, 'title_color'),
                'fontWeight' => in_array((string) ($s['title_typography_font_weight'] ?? ''), ['400', '500', '600', '700', '800', '900'], true)
                    ? (string) $s['title_typography_font_weight'] : null,
            ] + $this->headingTypography($s)))],

            'text-editor' => [$this->module('text', array_filter([
                'content' => (string) ($s['editor'] ?? ''),
                'textColor' => $this->settingColor($s, 'text_color'),
            ]))],

            'image' => $this->imageModule($s),

            'elementskit-creative-button' => [$this->module('button', array_filter([
                'text' => $this->plain($s['ekit_btn_text'] ?? ''),
                'url' => $this->url($s['ekit_btn_url'] ?? null),
                'style' => 'primary',
            ] + $this->buttonSkin($s)))],

            'counter' => [$this->module('stats', ['items' => [[
                'value' => (string) ($s['ending_number'] ?? '0'),
                'label' => $this->plain($s['title'] ?? ''),
                'prefix' => $this->plain($s['prefix'] ?? ''),
                'suffix' => $this->plain($s['suffix'] ?? ''),
            ]], 'columns' => 1, 'plain' => true])],

            'elementskit-funfact' => [$this->module('stats', ['items' => [[
                'value' => (string) ($s['ekit_funfact_number'] ?? '0'),
                'label' => $this->plain($s['ekit_funfact_title_text'] ?? ''),
                'prefix' => '',
                'suffix' => $this->plain(($s['ekit_funfact_super_text'] ?? '') . ($s['ekit_funfact_number_suffix'] ?? '')),
            ]], 'columns' => 1, 'plain' => true])],

            'elementskit-progressbar' => [$this->module('stats', ['items' => [[
                'value' => (string) ($s['ekit_progressbar_percentage'] ?? '0'),
                'label' => $this->plain($s['ekit_progressbar_title'] ?? ''),
                'prefix' => '', 'suffix' => '%',
            ]], 'columns' => 1, 'plain' => true])],

            'icon-list' => (function () use ($s) {
                $items = array_values(array_filter(array_map(
                    fn ($i) => $this->plain($i['text'] ?? ''), (array) ($s['icon_list'] ?? [])
                )));

                return $items === [] ? [] : [$this->module('html-embed', [
                    'html' => $this->checkListHtml($items, 'var(--color-text-muted,#5b6472)'),
                ])];
            })(),

            'text-path' => [$this->module('html-embed', ['html' => $this->circularTextHtml($this->plain($s['text'] ?? ''))])],

            'elementskit-icon-box', 'icon-box', 'image-box' => $this->iconBoxModules($s),

            'elementskit-accordion' => [$this->module('accordion', [
                'items' => array_values(array_filter(array_map(fn ($i) => [
                    'title' => $this->plain($i['acc_title'] ?? ''),
                    'content' => (string) ($i['acc_content'] ?? ''),
                ], (array) ($s['ekit_accordion_items'] ?? [])), fn ($i) => $i['title'] !== '')),
                'multiOpen' => false, 'iconStyle' => 'chevron',
            ])],

            'image-carousel' => $this->carouselModules($s),

            'elementskit-category-list' => $this->categoryTiles(),

            'elementskit-blog-posts' => [$this->module('latestposts', [
                'limit' => 3, 'columns' => 3, 'layout' => 'cards',
                'showImage' => true, 'showDate' => true, 'showExcerpt' => false, 'showCategory' => false,
            ])],

            'elementskit-contact-form7' => [$this->module('contact-form', [
                'fields' => [
                    ['label' => 'Име', 'type' => 'text', 'required' => true],
                    ['label' => 'Имейл', 'type' => 'email', 'required' => true],
                    ['label' => 'Телефон', 'type' => 'tel', 'required' => false],
                    ['label' => 'Съобщение', 'type' => 'textarea', 'required' => true],
                ],
                'submitText' => 'Изпрати', 'recipientEmail' => '',
            ])],

            'google_maps' => [$this->module('map', [
                'address' => $this->plain($s['address'] ?? ''), 'zoom' => 14, 'height' => '400px', 'style' => 'roadmap',
            ])],

            'elementskit-video' => [$this->module('video', array_filter([
                'url' => $this->url($s['ekit_video_link'] ?? null) ?? (string) ($s['ekit_video_url'] ?? ''),
                'autoplay' => false, 'muted' => true,
            ], fn ($v) => $v !== '' && $v !== null))],

            default => [],
        };

        $modules = array_values(array_filter($modules, fn ($m) => $m !== null && $this->hasContent($m)));

        if ($shared !== []) {
            foreach ($modules as $i => $m) {
                $modules[$i]['data'] = array_merge($m['data'], $shared);
            }
        }

        return $modules;
    }

    /** Source-site product categories as linked tile columns (context-fed). */
    private function categoryTiles(): array
    {
        $cats = array_slice((array) ($this->context['categories'] ?? []), 0, 6);
        if ($cats === []) {
            return [$this->module('categorylist', ['style' => 'cards', 'showCount' => true, 'parentOnly' => false])];
        }
        $images = [];
        foreach ($cats as $cat) {
            if (!empty($cat['image'])) {
                $images[] = ($this->importImage)($cat['image'], (string) ($cat['name'] ?? ''));
            }
        }

        // One compact band, like the source's category carousel.
        return $images === [] ? [] : [$this->module('gallery', [
            'images' => $images, 'layout' => 'grid', 'columns' => min(6, count($images)),
            'effects' => ['enabled' => true, 'hover' => ['enabled' => true, 'preset' => 'shine']],
            '__animation' => ['entrance' => 'fade', 'duration' => 700],
        ])];
    }

    /** Source-site project cards (context-fed): image + title + text, side by side. */
    private function projectCardsRow(): ?array
    {
        $projects = array_slice((array) ($this->context['projects'] ?? []), 0, 2);
        $columns = [];
        foreach ($projects as $pr) {
            $mods = [];
            if (!empty($pr['image'])) {
                $mods[] = $this->module('image', ['url' => ($this->importImage)($pr['image'], (string) ($pr['title'] ?? '')), 'alt' => (string) ($pr['title'] ?? ''), 'size' => 'large']);
            }
            if (!empty($pr['title'])) {
                $mods[] = $this->module('heading', ['text' => $this->plain($pr['title']), 'level' => 'h3']);
            }
            if (!empty($pr['text'])) {
                $mods[] = $this->module('text', ['content' => '<p>' . e($this->plain($pr['text'])) . '</p>']);
            }
            if ($mods !== []) {
                $columns[] = $this->column($mods);
            }
        }
        if ($columns === []) {
            return null;
        }

        return count($columns) >= 2
            ? $this->row('1/2+1/2', $columns)
            : $this->row('1', $columns);
    }

    private function imageModule(array $s): array
    {
        $url = $this->imageUrl($s['image'] ?? null);
        if ($url === null) {
            return [];
        }
        // Decorative trust-face/avatar clusters read as content images here —
        // stacked strangers add nothing to the migrated page.
        if (preg_match('#(trusted-client|avatar|profile-(pic|img))[^/]*$#i', $url) === 1) {
            return [];
        }
        $sizeMap = ['thumbnail' => 'small', 'medium' => 'medium', 'medium_large' => 'medium', 'large' => 'large', 'full' => 'large'];
        $data = ['url' => ($this->importImage)($url, ''), 'alt' => '', 'size' => $sizeMap[$s['image_size'] ?? 'full'] ?? 'large'];
        // Builder sites dress nearly every image the same way: a soft entrance
        // when it scrolls in and a hover response. Recreate both by default;
        // explicit per-widget animation settings still win via sharedProps.
        $data['effects'] = ['enabled' => true, 'hover' => ['enabled' => true, 'preset' => 'shine', 'duration' => 350, 'easing' => 'ease-out']];
        $data['__animation'] = ['entrance' => 'zoom', 'duration' => 700];

        return [$this->module('image', $data)];
    }

    /** Icon-box → icon-appropriate featuregrid item (merged later by siblings? kept simple: 1-item grid). */
    private function iconBoxModules(array $s): array
    {
        $title = $this->plain($s['ekit_icon_box_title_text'] ?? $s['title_text'] ?? '');
        $desc = $this->plain($s['ekit_icon_box_description_text'] ?? $s['description_text'] ?? '');
        if ($title === '' && $desc === '') {
            return [];
        }

        // ElementsKit header icon (SVG) → feature box (tile-left, icon-top, or a
        // real card when the widget carries its own solid box background).
        $hi = $s['ekit_icon_box_header_icons']['value'] ?? null;
        if (is_array($hi) && is_string($hi['url'] ?? null) && $hi['url'] !== '') {
            $position = ($s['ekit_icon_box_icon_position'] ?? 'top') === 'left' ? 'left' : 'top';
            $boxBg = $this->color($s['ekit_icon_box_infobox_bg_group_color'] ?? null);
            $boxBg = ($boxBg !== null && !$this->isTransparent($boxBg)) ? $boxBg : null;

            return [$this->module('html-embed', [
                'html' => $this->featureBoxHtml(($this->importImage)($hi['url'], $title), $title, $desc, $position, $boxBg),
            ])];
        }

        $modules = [];
        $img = $this->imageUrl($s['image'] ?? null);
        if ($img !== null) {
            $modules[] = $this->module('image', ['url' => ($this->importImage)($img, $title), 'alt' => $title, 'size' => 'large']);
        }
        if ($title !== '') {
            $modules[] = $this->module('heading', ['text' => $title, 'level' => 'h3']);
        }
        if ($desc !== '') {
            $modules[] = $this->module('text', ['content' => '<p>' . e($desc) . '</p>']);
        }

        return $modules;
    }

    private function carouselModules(array $s): array
    {
        $images = [];
        foreach ((array) ($s['carousel'] ?? []) as $img) {
            $u = is_array($img) ? ($img['url'] ?? null) : null;
            if (is_string($u) && $u !== '') {
                $images[] = ['src' => ($this->importImage)($u, ''), 'alt' => '', 'url' => ''];
            }
        }
        if ($images === []) {
            return [];
        }

        // Many small images → a logo strip; otherwise a gallery grid.
        // Canonical shapes: logostrip wants 'logos' as URL strings, gallery
        // wants plain URL-string images (objects are rejected by the editor).
        $urls = array_map(fn ($i) => $i['src'], $images);

        return count($urls) >= 4
            ? [$this->module('logostrip', ['logos' => array_slice($urls, 0, 9), 'grayscale' => false, 'columns' => min(6, count($urls))])]
            : [$this->module('gallery', ['images' => $urls, 'layout' => 'grid', 'columns' => min(3, count($urls))])];
    }

    // ── shared props (animation) ──

    private function sharedProps(array $s): array
    {
        $shared = [];
        $anim = strtolower((string) ($s['_animation'] ?? ''));
        if ($anim !== '' && isset(self::ANIM_MAP[$anim])) {
            $a = ['entrance' => self::ANIM_MAP[$anim], 'duration' => 600];
            $delay = (int) ($s['_animation_delay'] ?? 0);
            if ($delay > 0) {
                $a['delay'] = min(3000, $delay);
            }
            $shared['__animation'] = $a;
        }

        return $shared;
    }

    // ── node builders / helpers ──

    private function row(string $layout, array $columns): array
    {
        return [
            'id' => (string) Str::uuid(), 'type' => 'row', 'level' => 'row', 'order' => 0,
            'data' => ['layout' => $layout, 'gap' => '24px'], 'children' => $this->reorder($columns),
        ];
    }

    private function column(array $modules, array $cardData = []): array
    {
        return [
            'id' => (string) Str::uuid(), 'type' => 'column', 'level' => 'column', 'order' => 0,
            'data' => $cardData, 'children' => $this->reorder($modules),
        ];
    }

    /**
     * Card styling for a source column-container: reproduce a designed "card"
     * (background / border / radius / padding) either from the container's own
     * Elementor settings (e.g. glass service tiles) or, when it wraps a single
     * ElementsKit icon-box styled as a box, from that widget's box settings
     * (e.g. white feature cards). Returns column `data` overrides, or [] when
     * the container is not a card (so ordinary columns are untouched).
     */
    private function cardStyle(array $container): array
    {
        $s = $container['settings'] ?? [];
        $visual = [];
        $bg = null;

        $bgc = $this->color($s['background_color'] ?? null);
        if ($bgc !== null && ($s['background_background'] ?? '') === 'classic' && !$this->isTransparent($bgc)) {
            $bg = $bgc;
        }
        $borderColor = $this->color($s['border_color'] ?? null);
        if (($s['border_border'] ?? '') === 'solid' && $borderColor !== null) {
            $visual['borderStyle'] = 'solid';
            $visual['borderWidth'] = $this->edim($s['border_width'] ?? null) ?: '1px';
            $visual['borderColor'] = $borderColor;
        }
        if (($rad = $this->edim($s['border_radius'] ?? null)) !== '') {
            $visual['borderRadius'] = $rad;
        }
        $pad = $this->edim($s['padding'] ?? null);

        // NB: a single container can wrap several ElementsKit icon-boxes, so we
        // deliberately do NOT promote an icon-box's own box styling to the whole
        // column (that paints one oversized empty card). Card styling is taken
        // only from the container itself — reliable for glass/tile grids.

        // A container is only a "card" when it has a fill or a rounded, bordered
        // frame — otherwise leave the column unstyled so ordinary layout columns
        // (which may carry incidental padding) are unaffected.
        $isCard = $bg !== null || (isset($visual['borderColor']) && isset($visual['borderRadius']));
        if (!$isCard) {
            return [];
        }

        $data = [];
        if ($bg !== null) {
            $data['background_color'] = $bg;
        }
        if ($pad !== '') {
            $data['padding'] = $pad;
        }
        if ($visual !== []) {
            $data['__style'] = ['visual' => $visual];
        }

        return $data;
    }

    /** Per-widget heading typography (font-size/line-height/letter-spacing) →
     * heading-module fields, so a widget that overrides the kit scale (e.g. a
     * 66px hero title) renders at its real size instead of the theme default. */
    private function headingTypography(array $s): array
    {
        $out = [
            'fontSize' => $this->edim($s['typography_font_size'] ?? $s['title_typography_font_size'] ?? null),
            'lineHeight' => $this->edim($s['typography_line_height'] ?? $s['title_typography_line_height'] ?? null),
            'letterSpacing' => $this->edim($s['typography_letter_spacing'] ?? $s['title_typography_letter_spacing'] ?? null),
        ];

        return array_filter($out, fn ($v) => $v !== '');
    }

    /** A fully-transparent 8-digit hex (alpha 00) is not a card fill. */
    private function isTransparent(string $hex): bool
    {
        return preg_match('/^#[0-9a-f]{6}00$/i', $hex) === 1;
    }

    /** Elementor dimension object → CSS shorthand ('' when unset or all-zero). */
    private function edim(mixed $v): string
    {
        if (!is_array($v)) {
            return '';
        }
        $unit = in_array($v['unit'] ?? 'px', ['px', 'em', 'rem', '%'], true) ? $v['unit'] : 'px';
        if (isset($v['size']) && is_numeric($v['size'])) {
            return ((float) $v['size'] === 0.0) ? '' : ((float) $v['size']) . $unit;
        }
        $sides = ['top', 'right', 'bottom', 'left'];
        $nums = array_map(fn ($k) => is_numeric($v[$k] ?? null) ? (float) $v[$k] : 0.0, $sides);
        if (array_sum($nums) === 0.0) {
            return '';
        }
        // Collapse equal sides to a single value — the style pipeline (safeDim)
        // accepts one dimension, not 4-value shorthand.
        if (count(array_unique($nums)) === 1) {
            return $nums[0] . $unit;
        }

        return implode(' ', array_map(fn ($n) => $n . $unit, $nums));
    }

    private function module(string $type, array $data): array
    {
        return [
            'id' => (string) Str::uuid(), 'type' => $type, 'level' => 'module', 'order' => 0,
            'data' => $data, 'children' => [],
        ];
    }

    private function hasContent(array $m): bool
    {
        $d = $m['data'];

        return match ($m['type']) {
            'heading' => trim((string) ($d['text'] ?? '')) !== '',
            'text' => trim(strip_tags((string) ($d['content'] ?? ''))) !== '',
            'button' => trim((string) ($d['text'] ?? '')) !== '',
            'image' => ($d['url'] ?? '') !== '',
            'list' => ($d['items'] ?? []) !== [],
            'accordion', 'stats' => ($d['items'] ?? []) !== [],
            'logostrip' => ($d['logos'] ?? []) !== [],
            'gallery' => ($d['images'] ?? []) !== [],
            'map' => trim((string) ($d['address'] ?? '')) !== '',
            'video' => ($d['url'] ?? '') !== '',
            default => true,
        };
    }

    private function reorder(array $nodes): array
    {
        return array_values(array_map(function ($node, $i) {
            $node['order'] = $i;

            return $node;
        }, $nodes, array_keys($nodes)));
    }

    private function plain(mixed $v): string
    {
        return trim(strip_tags((string) $v));
    }

    /** A color setting: literal value, or the kit global the __globals__ ref points to. */
    private function settingColor(array $s, string $key): ?string
    {
        $literal = $this->color($s[$key] ?? null);
        if ($literal !== null) {
            return $literal;
        }
        $ref = $s['__globals__'][$key] ?? '';
        if (is_string($ref) && preg_match('#globals/colors\?id=([A-Za-z0-9_-]+)#', str_replace('\\/', '/', $ref), $m)) {
            return $this->color($this->globalColors[$m[1]] ?? null);
        }

        return null;
    }

    private function color(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($v)) === 1 ? strtolower(trim($v)) : null;
    }

    /** The source kit's accent colour (blue tiles, check icons, badge…), or a
     * sensible default — so migrated accents follow the source, not a constant. */
    private function accent(): string
    {
        return $this->color($this->globalColors['accent'] ?? null) ?? '#2f6df6';
    }

    /**
     * A color where an Elementor kit-global reference WINS over the stored
     * literal — Elementor keeps the old literal when you switch a control to a
     * global, and renders the global. settingColor() prefers the literal (right
     * for plain fields); this is for fields that carry a global (e.g. buttons).
     */
    private function colorGlobalFirst(array $s, string $key): ?string
    {
        $ref = $s['__globals__'][$key] ?? '';
        if (is_string($ref) && preg_match('#globals/colors\?id=([A-Za-z0-9_-]+)#', str_replace('\\/', '/', $ref), $m)) {
            $global = $this->color($this->globalColors[$m[1]] ?? null);
            if ($global !== null) {
                return $global;
            }
        }

        return $this->color($s[$key] ?? null);
    }

    private function url(mixed $v): ?string
    {
        $u = is_array($v) ? ($v['url'] ?? null) : (is_string($v) ? $v : null);

        return is_string($u) && preg_match('#^(https?://|/)#i', $u) === 1 ? $u : null;
    }

    private function imageUrl(mixed $v): ?string
    {
        $u = is_array($v) ? ($v['url'] ?? null) : null;

        return is_string($u) && preg_match('#^https?://#i', $u) === 1 ? $u : null;
    }
}
