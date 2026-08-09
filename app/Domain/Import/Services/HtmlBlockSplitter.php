<?php

namespace App\Domain\Import\Services;

use Illuminate\Support\Str;

/**
 * Splits classic-editor WordPress post HTML into a CMS block tree.
 *
 * Classic content is stored semi-plain (paragraphs are blank-line separated,
 * not <p>-wrapped — WordPress applies wpautop() at render), so we replicate
 * wpautop first, then split the resulting HTML at block boundaries:
 *  - headings (h1–h6)  → native `heading` blocks (SEO document structure)
 *  - standalone images → native `image` blocks bound to a CMS asset
 *    (so the publish WebP/<picture>/dimensions pipeline optimizes them)
 *  - everything else   → `text` blocks holding the rich HTML verbatim,
 *    with inline <img> rewritten to their CMS asset serve URL.
 *
 * $assetsByBasename maps a lowercased upload basename (e.g. 'photo.jpg') to
 * ['id' => asset uuid, 'w' => int|null, 'h' => int|null, 'alt' => string].
 */
class HtmlBlockSplitter
{
    private const BLOCK_TAGS = 'table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary';

    public function split(string $html, array $assetsByBasename, string $siteId): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $html = $this->wpautop($html);

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // Wrapper + explicit UTF-8 so Cyrillic survives; NOIMPLIED/NODEFDTD keeps
        // libxml from injecting <html><body>.
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="sp-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('sp-root');
        if (!$root) {
            return $this->textBlock($html, $assetsByBasename, $siteId, 0) ? [$this->textBlock($html, $assetsByBasename, $siteId, 0)] : [];
        }

        $blocks = [];
        $order = 0;
        $textBuf = '';

        $flush = function () use (&$textBuf, &$blocks, &$order, $assetsByBasename, $siteId) {
            $buf = trim($textBuf);
            $textBuf = '';
            if ($buf === '' || trim(strip_tags($buf)) === '' && !str_contains($buf, '<img')) {
                return;
            }
            $block = $this->textBlock($buf, $assetsByBasename, $siteId, $order);
            if ($block) {
                $blocks[] = $block;
                $order++;
            }
        };

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $textBuf .= $node->ownerDocument->saveHTML($node);
                continue;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($node->nodeName);

            if (preg_match('/^h([1-6])$/', $tag, $m)) {
                $flush();
                $text = trim($this->innerText($node));
                if ($text !== '') {
                    $blocks[] = $this->wrap(['type' => 'heading', 'data' => ['text' => $text, 'level' => "h{$m[1]}"]], $order++);
                }
                continue;
            }

            // Standalone image: <img>, <figure> with an img, or a <p> whose only
            // meaningful content is an img.
            $img = $this->soleImage($node);
            if ($img) {
                $flush();
                $imageBlock = $this->imageBlock($img, $node, $assetsByBasename, $order);
                if ($imageBlock) {
                    $blocks[] = $imageBlock;
                    $order++;
                }
                continue;
            }

            if ($tag === 'hr') {
                $flush();
                $blocks[] = $this->wrap(['type' => 'divider', 'data' => []], $order++);
                continue;
            }

            // Everything else stays as rich HTML in a text block.
            $textBuf .= $node->ownerDocument->saveHTML($node);
        }
        $flush();

        return $blocks;
    }

    /** A node that is (or wraps) exactly one image and no other real content. */
    private function soleImage(\DOMElement $node): ?\DOMElement
    {
        $tag = strtolower($node->nodeName);
        if ($tag === 'img') {
            return $node;
        }
        if (!in_array($tag, ['figure', 'p', 'div'], true)) {
            return null;
        }
        $imgs = $node->getElementsByTagName('img');
        if ($imgs->length !== 1) {
            return null;
        }
        // No meaningful text alongside the image (a caption in <figcaption> is fine).
        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        // figcaption text is allowed; strip it before checking.
        foreach ($node->getElementsByTagName('figcaption') as $fc) {
            $text = trim(str_replace(trim($fc->textContent), '', $text));
        }
        return $text === '' ? $imgs->item(0) : null;
    }

    private function imageBlock(\DOMElement $img, \DOMElement $container, array $assetsByBasename, int $order): ?array
    {
        $src = $img->getAttribute('src');
        $alt = $img->getAttribute('alt');
        $caption = '';
        if (strtolower($container->nodeName) === 'figure') {
            foreach ($container->getElementsByTagName('figcaption') as $fc) {
                $caption = trim($fc->textContent);
                break;
            }
        }

        $asset = $this->resolveAsset($src, $assetsByBasename);
        $data = [
            'alt' => $alt ?: ($asset['alt'] ?? ''),
            'caption' => $caption,
        ];
        if ($asset) {
            $data['asset_id'] = $asset['id'];
            $data['url'] = "/api/v1/sites/{$asset['site_id']}/assets/{$asset['id']}/serve";
            if (!empty($asset['w'])) $data['width'] = (string) $asset['w'];
            if (!empty($asset['h'])) $data['height'] = (string) $asset['h'];
        } elseif ($src) {
            // Unmatched (external or missing) image — keep the original src so the
            // post still shows it, but it won't be optimized.
            $data['url'] = $src;
        } else {
            return null;
        }

        return $this->wrap(['type' => 'image', 'data' => $data], $order);
    }

    /** Rewrite inline <img> in a text fragment to CMS asset serve URLs. */
    private function textBlock(string $htmlFragment, array $assetsByBasename, string $siteId, int $order): ?array
    {
        $content = preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function ($m) use ($assetsByBasename) {
                $asset = $this->resolveAsset($m[2], $assetsByBasename);
                if (!$asset) {
                    return $m[0];
                }
                $url = "/api/v1/sites/{$asset['site_id']}/assets/{$asset['id']}/serve";
                return $m[1] . $url . $m[3];
            },
            $htmlFragment
        );

        $content = trim($content);
        if ($content === '' || (trim(strip_tags($content)) === '' && !str_contains($content, '<img'))) {
            return null;
        }

        return $this->wrap(['type' => 'text', 'data' => ['content' => $content]], $order);
    }

    /** Resolve an <img> src to a CMS asset via its upload basename. */
    private function resolveAsset(string $src, array $assetsByBasename): ?array
    {
        if ($src === '') {
            return null;
        }
        // Strip query/fragment, take basename, drop WP -WxH size suffix so a
        // scaled inline image maps back to its original attachment.
        $path = parse_url($src, PHP_URL_PATH) ?: $src;
        $base = strtolower(rawurldecode(basename($path)));
        if (isset($assetsByBasename[$base])) {
            return $assetsByBasename[$base];
        }
        $stripped = preg_replace('/-\d{1,4}x\d{1,4}(\.\w+)$/', '$1', $base);
        return $assetsByBasename[$stripped] ?? null;
    }

    private function innerText(\DOMElement $node): string
    {
        return preg_replace('/\s+/u', ' ', $node->textContent);
    }

    private function wrap(array $block, int $order): array
    {
        $block['children'] = [];
        $block['order'] = $order;
        $block['id'] = Str::uuid()->toString();
        return $block;
    }

    /**
     * Minimal wpautop(): turn blank-line-separated plain text into <p>…</p>,
     * single newlines into <br>, without wrapping block-level elements.
     */
    public function wpautop(string $pee): string
    {
        $pee = str_replace(["\r\n", "\r"], "\n", $pee) . "\n";
        $b = self::BLOCK_TAGS;

        // Surround block-level tags with double newlines so they split out.
        $pee = preg_replace('!(<(?:' . $b . ')(?:\s[^>]*)?/?>)!i', "\n\n$1", $pee);
        $pee = preg_replace('!(</(?:' . $b . ')>)!i', "$1\n\n", $pee);
        $pee = preg_replace("/\n\n+/", "\n\n", (string) $pee);

        $chunks = preg_split('/\n\s*\n/', (string) $pee, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ($chunks as $chunk) {
            $t = trim($chunk);
            if ($t === '') {
                continue;
            }
            if (preg_match('!^</?(?:' . $b . ')(?:\s[^>]*)?/?>!i', $t)) {
                $out .= $t . "\n";
            } else {
                // single newlines inside a text paragraph become <br>
                $out .= '<p>' . nl2br($t) . "</p>\n";
            }
        }

        return $out;
    }
}
