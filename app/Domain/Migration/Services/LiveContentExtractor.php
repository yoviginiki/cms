<?php

namespace App\Domain\Migration\Services;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Turns an origin page's RENDERED HTML into native block data + page meta.
 *
 * This is the spider's core: builder plugins (Divi/Elementor/…) store layouts
 * as shortcode soup in the database, but the *rendered* DOM is honest. We walk
 * the content area and emit heading/paragraph/image blocks, keeping inline
 * markup (links survive → internal links get recreated), and read the SEO meta
 * the WXR export doesn't carry (title tag, meta description, og:image).
 *
 * Proven against heikotera.com (Divi): 57/59 documents extracted cleanly.
 */
class LiveContentExtractor
{
    /** Content-area candidates, most specific first. */
    private const CONTENT_ROOTS = [
        "//div[contains(@class,'entry-content')]",
        "//div[@id='main-content']",
        '//article',
        '//main',
    ];

    /** Ancestors whose subtree is chrome, not content. */
    private const SKIP_ANCESTOR_CLASSES =
        '/\s(et_pb_sidebar|sidebar|related|nav-single|comment|et_social|sharedaddy|wp-block-comments|widget)\S*\s/';

    /** Ancestors that ARE a widget captured whole — inner nodes must be skipped. */
    private const WIDGET_ANCESTOR_CLASSES =
        '/\s(et_pb_accordion|et_pb_tabs|et_pb_gallery|wp-block-gallery|et_pb_counters|et_pb_number_counter|et_pb_circle_counter)\s/';

    /**
     * @return array{
     *   blocks: array<int, array>,
     *   meta: array{title: ?string, description: ?string, og_image: ?string},
     *   background_images: string[],
     * }
     */
    public function extract(string $html, string $pageTitle, ?Site $assetSite = null): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new \DOMXPath($doc);

        return [
            'blocks' => $this->extractBlocks($doc, $xp, $pageTitle, $assetSite),
            'meta' => $this->extractMeta($xp),
            'background_images' => $this->extractBackgroundImages($html),
        ];
    }

    /** Map a remote image URL to an imported Asset (by original filename). */
    public function assetForUrl(Site $site, string $src): ?array
    {
        $name = urldecode(basename(parse_url($src, PHP_URL_PATH) ?? ''));
        // strip WordPress size suffix (-300x200.jpg)
        $base = preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', $name);
        foreach (array_unique([$name, $base]) as $n) {
            if ($n === '') {
                continue;
            }
            $asset = Asset::where('site_id', $site->id)->where('original_name', $n)->first();
            if ($asset) {
                return [
                    'url' => "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve",
                    'asset_id' => $asset->id,
                    'original_name' => $base,
                ];
            }
        }

        return null;
    }

    private function extractBlocks(\DOMDocument $doc, \DOMXPath $xp, string $pageTitle, ?Site $assetSite): array
    {
        $root = null;
        foreach (self::CONTENT_ROOTS as $q) {
            $root = $xp->query($q)->item(0);
            if ($root) {
                break;
            }
        }
        if (!$root) {
            return [];
        }

        $seen = [];

        return $this->walk($doc, $xp, $root, $pageTitle, $assetSite, $seen, allowColumns: true);
    }

    /**
     * Walk a subtree into blocks. Multi-column builder rows (Divi et_pb_row
     * with ≥2 et_pb_column) are preserved as a '_columns' pseudo-block —
     * flattening them was the biggest structural infidelity of the spider.
     */
    private function walk(\DOMDocument $doc, \DOMXPath $xp, \DOMElement $root, string $pageTitle, ?Site $assetSite, array &$seen, bool $allowColumns): array
    {
        $blocks = [];
        $multiColRows = new \SplObjectStorage();
        // standalone builder buttons (et_pb_button, wp-block-button__link, …)
        // are anchors OUTSIDE any text node — capture them as button blocks.
        // Builder accordions (Divi et_pb_accordion, native <details>) are
        // captured whole as accordion blocks — walking their text nodes would
        // flatten an interactive widget into static headings + paragraphs.
        // Builder widget containers we reproduce as native blocks instead of
        // flattening: accordions, tabs (→ accordion, the static-faithful form),
        // galleries (→ gallery), number/circle counters (→ stats). A helper
        // keeps the has-class XPath predicate readable.
        $has = fn (string $c) => "contains(concat(' ',normalize-space(@class),' '),' {$c} ')";
        $widgetContainers = "|.//div[{$has('et_pb_accordion')} or {$has('et_pb_tabs')}"
            . " or {$has('et_pb_gallery')} or {$has('wp-block-gallery')}"
            . " or {$has('et_pb_counters')} or {$has('et_pb_number_counter')}"
            . " or {$has('et_pb_circle_counter')}]";
        $nodes = $xp->query(
            './/h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//p|.//ul|.//ol|.//img|.//blockquote'
            . "|.//a[contains(@class,'button') or contains(@class,'btn')]"
            . $widgetContainers
            . ($allowColumns ? "|.//div[{$has('et_pb_row')}]" : ''),
            $root
        );

        foreach ($nodes as $node) {
            // whole lists/quotes are emitted at their own node — skip members
            for ($anc = $node->parentNode; $anc; $anc = $anc->parentNode) {
                if (in_array(strtolower($anc->nodeName), ['ul', 'ol', 'blockquote'], true)) {
                    continue 2;
                }
            }
            for ($anc = $node->parentNode; $anc instanceof \DOMElement; $anc = $anc->parentNode) {
                $ancClass = ' ' . $anc->getAttribute('class') . ' ';
                if (preg_match(self::SKIP_ANCESTOR_CLASSES, $ancClass)) {
                    continue 2;
                }
                // members of a widget we capture whole (accordion/tabs/gallery/
                // counters) must not also be walked as loose content
                if (preg_match(self::WIDGET_ANCESTOR_CLASSES, $ancClass)) {
                    continue 2;
                }
                // members of a captured multi-column row are extracted per column
                if ($anc instanceof \DOMElement && $multiColRows->contains($anc)) {
                    continue 2;
                }
            }

            $tag = strtolower($node->nodeName);

            if ($tag === 'div') { // matched only for widget/row containers
                $class = ' ' . $node->getAttribute('class') . ' ';
                if (str_contains($class, ' et_pb_accordion ')) {
                    $accordion = $this->extractAccordion($doc, $xp, $node);
                    if ($accordion !== null) {
                        $blocks[] = $accordion;
                    }
                } elseif (str_contains($class, ' et_pb_tabs ')) {
                    $tabs = $this->extractTabs($doc, $xp, $node);
                    if ($tabs !== null) {
                        $blocks[] = $tabs;
                    }
                } elseif (str_contains($class, ' et_pb_gallery ') || str_contains($class, ' wp-block-gallery ')) {
                    $gallery = $this->extractGallery($xp, $node, $assetSite);
                    if ($gallery !== null) {
                        $blocks[] = $gallery;
                    }
                } elseif (str_contains($class, ' et_pb_counters ') || str_contains($class, ' et_pb_number_counter ') || str_contains($class, ' et_pb_circle_counter ')) {
                    $stats = $this->extractCounters($xp, $node);
                    if ($stats !== null) {
                        $blocks[] = $stats;
                    }
                } elseif ($allowColumns && str_contains($class, ' et_pb_row ')) {
                    // trial-extract per column on a COPY of the dedup set — a
                    // row that doesn't qualify must leave its content for the
                    // normal walk, not have it swallowed as "already seen"
                    $trialSeen = $seen;
                    $columns = [];
                    foreach ($xp->query("./div[contains(concat(' ',normalize-space(@class),' '),' et_pb_column ')]", $node) as $col) {
                        // nested rows inside a column are flattened (depth 1)
                        $colBlocks = $this->walk($doc, $xp, $col, $pageTitle, $assetSite, $trialSeen, allowColumns: false);
                        if ($colBlocks !== []) {
                            $columns[] = $colBlocks;
                        }
                    }
                    if (count($columns) >= 2) {
                        $seen = $trialSeen;
                        $multiColRows->attach($node);
                        $blocks[] = $this->block('_columns', []) + ['columns' => $columns];
                    }
                    // single/empty columns: fall through — children get walked normally
                }
                continue;
            }

            if ($tag === 'a') {
                // inline links inside a paragraph are captured with the <p>
                for ($anc = $node->parentNode; $anc; $anc = $anc->parentNode) {
                    if (strtolower($anc->nodeName) === 'p') {
                        continue 2;
                    }
                }
                $text = trim($node->textContent);
                $href = $node->getAttribute('href');
                if ($text === '' || $href === '' || isset($seen['btn:' . $text])) {
                    continue;
                }
                $seen['btn:' . $text] = true;
                $blocks[] = $this->block('button', ['text' => $text, 'url' => $href, 'style' => 'primary']);
                continue;
            }

            if ($tag === 'img') {
                $src = $node->getAttribute('data-src') ?: $node->getAttribute('src');
                if (!$src || str_starts_with($src, 'data:') || str_contains($src, 'logo')) {
                    continue;
                }
                $mapped = $assetSite ? $this->assetForUrl($assetSite, $src) : null;
                $data = $mapped
                    ? ['url' => $mapped['url'], 'asset_id' => $mapped['asset_id']]
                    : ['url' => $src, 'asset_id' => null];
                $b = $this->block('image', $data + [
                    'alt' => $node->getAttribute('alt') ?: $pageTitle,
                    'size' => 'large',
                ]);
                $b['_imgname'] = $mapped['original_name']
                    ?? basename(parse_url($src, PHP_URL_PATH) ?? '');
                $blocks[] = $b;
                continue;
            }

            $inner = '';
            foreach ($node->childNodes as $c) {
                $inner .= $doc->saveHTML($c);
            }
            $inner = strip_tags($inner, '<a><strong><em><b><i><br><span><li><ul><ol>');
            $text = trim(html_entity_decode(strip_tags($inner)));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }
            $seen[$text] = true;

            if ($tag[0] === 'h') {
                // the page title reappears as the builder's header h1 — the
                // rebuilder adds its own title hero, so drop the duplicate
                if (mb_strtolower($text) === mb_strtolower(trim($pageTitle))) {
                    continue;
                }
                // a linked heading must keep its link (heading blocks are
                // plain text) — emit a bold linked paragraph instead
                if (str_contains($inner, '<a ')) {
                    $blocks[] = $this->block('paragraph', ['content' => "<p><strong>{$inner}</strong></p>"]);
                } else {
                    $blocks[] = $this->block('heading', ['text' => $text, 'level' => $tag === 'h1' ? 'h2' : $tag]);
                }
            } elseif ($tag === 'ul' || $tag === 'ol') {
                $blocks[] = $this->block('paragraph', ['content' => "<{$tag}>{$inner}</{$tag}>"]);
            } elseif ($tag === 'blockquote') {
                $blocks[] = $this->block('paragraph', ['content' => "<blockquote>{$inner}</blockquote>"]);
            } else {
                if (preg_match('/^(by\s|©|Powered by)/u', $text)) {
                    continue;
                }
                $blocks[] = $this->block('paragraph', ['content' => "<p>{$inner}</p>"]);
            }
        }

        return $blocks;
    }

    /**
     * Divi accordion → native accordion block. Item title lives in
     * .et_pb_toggle_title, body in .et_pb_toggle_content; an initially-open
     * first item carries et_pb_toggle_open.
     */
    private function extractAccordion(\DOMDocument $doc, \DOMXPath $xp, \DOMElement $container): ?array
    {
        $items = [];
        $openFirst = false;
        $toggles = $xp->query(".//div[contains(concat(' ',normalize-space(@class),' '),' et_pb_toggle ')]", $container);

        foreach ($toggles as $i => $toggle) {
            $titleNode = $xp->query(".//*[contains(@class,'et_pb_toggle_title')]", $toggle)->item(0);
            $contentNode = $xp->query(".//div[contains(@class,'et_pb_toggle_content')]", $toggle)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
            if ($title === '' || !$contentNode) {
                continue;
            }
            $inner = '';
            foreach ($contentNode->childNodes as $c) {
                $inner .= $doc->saveHTML($c);
            }
            $items[] = [
                'title' => $title,
                'content' => strip_tags($inner, '<a><strong><em><b><i><br><p><ul><ol><li>'),
            ];
            if ($i === 0 && str_contains($toggle->getAttribute('class'), 'et_pb_toggle_open')) {
                $openFirst = true;
            }
        }

        return $items === []
            ? null
            : $this->block('accordion', ['items' => $items, 'openFirst' => $openFirst]);
    }

    /**
     * Divi tabs → accordion block. Tabbed panels can't be reproduced statically
     * (the native tabs block only renders panel 0 without JS), so we map to an
     * accordion, which keeps every panel's title AND content readable and
     * crawlable — the faithful choice for content, not just the first tab.
     * Titles live in .et_pb_tabs_controls li a; panels in .et_pb_tab_content.
     */
    private function extractTabs(\DOMDocument $doc, \DOMXPath $xp, \DOMElement $container): ?array
    {
        $titles = [];
        foreach ($xp->query(".//ul[contains(@class,'et_pb_tabs_controls')]//li", $container) as $li) {
            $titles[] = trim($li->textContent);
        }

        $items = [];
        $panels = $xp->query(".//div[contains(concat(' ',normalize-space(@class),' '),' et_pb_tab_content ')]", $container);
        foreach ($panels as $i => $panel) {
            $inner = '';
            foreach ($panel->childNodes as $c) {
                $inner .= $doc->saveHTML($c);
            }
            $content = trim(strip_tags($inner, '<a><strong><em><b><i><br><p><ul><ol><li>'));
            $title = trim($titles[$i] ?? '') ?: 'Tab ' . ($i + 1);
            if ($content === '') {
                continue;
            }
            $items[] = ['title' => $title, 'content' => $content];
        }

        return $items === []
            ? null
            : $this->block('accordion', ['items' => $items, 'openFirst' => true]);
    }

    /**
     * Divi/WP gallery → native gallery block. Prefers the full-size link target
     * (a>img) over the rendered thumbnail, then maps each to an imported Asset
     * by filename so the static build serves local copies.
     */
    private function extractGallery(\DOMXPath $xp, \DOMElement $container, ?Site $assetSite): ?array
    {
        $images = [];
        $seen = [];
        foreach ($xp->query('.//img', $container) as $img) {
            // full-size href if the image is wrapped in a lightbox link
            $link = null;
            for ($anc = $img->parentNode; $anc instanceof \DOMElement; $anc = $anc->parentNode) {
                if (strtolower($anc->nodeName) === 'a' && $anc->getAttribute('href') !== '') {
                    $link = $anc->getAttribute('href');
                    break;
                }
            }
            $thumb = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            $src = $link && preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $link) ? $link : $thumb;
            if (!$src || str_starts_with($src, 'data:') || isset($seen[$src])) {
                continue;
            }
            $seen[$src] = true;
            $mapped = $assetSite ? $this->assetForUrl($assetSite, $src) : null;
            // Gallery stores images as URL STRINGS (canonical shape the editor
            // and validator expect). Serve URLs become static at publish via
            // AssetPublisher's rendered-HTML rewrite, same as image blocks.
            $images[] = $mapped['url'] ?? $src;
        }

        return count($images) < 2
            ? null // a single image is just an image block, not a gallery
            : $this->block('gallery', ['images' => $images, 'layout' => 'grid', 'columns' => min(4, count($images)), 'gap' => '12px']);
    }

    /**
     * Divi number/circle counters → native stats block. The counted value lives
     * in data-number-value (falls back to .percent-value text), the unit in
     * .percent-sign, the caption in .title.
     */
    private function extractCounters(\DOMXPath $xp, \DOMElement $container): ?array
    {
        // The matched node may be the wrapping .et_pb_counters OR a single
        // counter — normalise to the list of individual counter elements.
        $containerClass = ' ' . $container->getAttribute('class') . ' ';
        $counters = (str_contains($containerClass, ' et_pb_number_counter ') || str_contains($containerClass, ' et_pb_circle_counter '))
            ? [$container]
            : iterator_to_array($xp->query(".//div[contains(concat(' ',normalize-space(@class),' '),' et_pb_number_counter ') or contains(concat(' ',normalize-space(@class),' '),' et_pb_circle_counter ')]", $container));

        $items = [];
        foreach ($counters as $counter) {
            $value = $counter->getAttribute('data-number-value');
            if ($value === '') {
                $valueNode = $xp->query(".//*[contains(@class,'percent-value')]", $counter)->item(0);
                $value = $valueNode ? trim($valueNode->textContent) : '';
            }
            if ($value === '') {
                continue;
            }
            $signNode = $xp->query(".//*[contains(@class,'percent-sign')]", $counter)->item(0);
            $titleNode = $xp->query(".//*[contains(@class,'title')]", $counter)->item(0);
            $items[] = [
                'value' => $value,
                'suffix' => $signNode ? trim($signNode->textContent) : '',
                'prefix' => '',
                'label' => $titleNode ? trim($titleNode->textContent) : '',
            ];
        }

        return $items === []
            ? null
            : $this->block('stats', ['items' => $items, 'columns' => min(4, count($items)), 'gap' => '24px']);
    }

    private function extractMeta(\DOMXPath $xp): array
    {
        $get = function (string $q, string $attr = 'content') use ($xp): ?string {
            $n = $xp->query($q)->item(0);
            $v = $n instanceof \DOMElement ? trim($n->getAttribute($attr)) : null;

            return $v !== '' ? $v : null;
        };
        $titleNode = $xp->query('//title')->item(0);

        return [
            'title' => $titleNode ? trim($titleNode->textContent) : null,
            'description' => $get("//meta[@name='description']")
                ?? $get("//meta[@property='og:description']"),
            'og_image' => $get("//meta[@property='og:image']"),
        ];
    }

    /** @return string[] absolute URLs used as CSS section backgrounds */
    private function extractBackgroundImages(string $html): array
    {
        preg_match_all('#background-image:[^;}]*url\((?:&\#0?39;|&quot;|[\'"])?(https?://[^\'")]+)#i', $html, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    private function block(string $type, array $data): array
    {
        static $order = 0;

        return [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => $order++,
            'data' => $data,
            'children' => [],
        ];
    }
}
