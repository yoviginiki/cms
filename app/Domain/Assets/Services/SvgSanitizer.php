<?php

namespace App\Domain\Assets\Services;

/**
 * Server-side SVG scrubbing at upload time (gap S7 in
 * layer-inspector-gap-analysis.md — SVGs were stored raw).
 *
 * DOM-based: removes script/animation-timing/foreignObject elements, all
 * event-handler attributes, and any URL-bearing attribute that is not a
 * local fragment or a plain relative/https image path. Deliberately strict —
 * a stripped decorative feature is acceptable, stored XSS is not.
 */
class SvgSanitizer
{
    private const REMOVE_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video',
        'animate', 'animatemotion', 'animatetransform', 'set', 'handler', 'listener',
    ];

    private const URL_ATTRIBUTES = ['href', 'xlink:href', 'src', 'data'];

    /**
     * @return string|null sanitized SVG, or null when the input isn't parseable SVG
     */
    public function sanitize(string $svg): ?string
    {
        // strip BOM + anything before the first element; reject entity tricks (XXE / billion laughs)
        $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg);
        if (stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) {
            $svg = preg_replace('/<!DOCTYPE[^>[]*(\[[^\]]*\])?>/is', '', $svg);
            if (stripos($svg, '<!ENTITY') !== false) {
                return null;
            }
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET); // no network; entities stay unexpanded (rejected above anyway)
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded || strtolower($dom->documentElement->localName ?? '') !== 'svg') {
            return null;
        }

        $this->scrub($dom->documentElement);

        return $dom->saveXML($dom->documentElement);
    }

    private function scrub(\DOMElement $el): void
    {
        // depth-first over a static child list (we mutate while walking)
        foreach (iterator_to_array($el->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                if (in_array(strtolower($child->localName), self::REMOVE_ELEMENTS, true)) {
                    $el->removeChild($child);
                    continue;
                }
                $this->scrub($child);
            } elseif ($child instanceof \DOMProcessingInstruction || $child instanceof \DOMComment) {
                $el->removeChild($child);
            }
        }

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = trim($attr->nodeValue ?? '');

            // event handlers (onclick, onload, …)
            if (str_starts_with($name, 'on')) {
                $el->removeAttributeNode($attr);
                continue;
            }
            // URL-bearing attributes: local fragments and SITE-RELATIVE paths
            // only — no absolute/protocol-relative URLs (external inclusion)
            if (in_array($name, self::URL_ATTRIBUTES, true)) {
                $ok = $value === ''
                    || str_starts_with($value, '#')
                    || preg_match('#^/(?!/)[^\s]*$#', $value)
                    || preg_match('#^[a-z0-9_./-]+\.(png|jpe?g|gif|webp|svg)$#i', $value);
                if (!$ok) {
                    $el->removeAttributeNode($attr);
                }
                continue;
            }
            // style attributes: kill url()/expression()/behavior payloads
            if ($name === 'style' && preg_match('/(url\s*\(|expression|behavior|@import)/i', $value)) {
                $el->removeAttributeNode($attr);
            }
        }
    }
}
