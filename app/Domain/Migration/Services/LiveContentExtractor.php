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

        $blocks = [];
        $seen = [];
        // standalone builder buttons (et_pb_button, wp-block-button__link, …)
        // are anchors OUTSIDE any text node — capture them as button blocks
        $nodes = $xp->query(
            './/h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//p|.//ul|.//ol|.//img|.//blockquote'
            . "|.//a[contains(@class,'button') or contains(@class,'btn')]",
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
                if (preg_match(self::SKIP_ANCESTOR_CLASSES, ' ' . $anc->getAttribute('class') . ' ')) {
                    continue 2;
                }
            }

            $tag = strtolower($node->nodeName);

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
