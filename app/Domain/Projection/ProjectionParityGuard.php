<?php

namespace App\Domain\Projection;

/**
 * Parity guard (Prime Directive 4): every fact in the PUBLIC projection must be
 * present in the rendered HTML of the same page. Divergence between structured
 * data and visible content is penalised by search engines.
 *
 * The guard is pure (no I/O). It returns the list of public text values that do
 * NOT appear in the HTML; an empty list means parity holds.
 */
class ProjectionParityGuard
{
    /** Keys in a schema node that are URLs / ids / structural, not visible prose. */
    private const NON_TEXT_KEYS = ['@type', '@context', 'contentUrl', 'image', 'url'];

    /**
     * @param array  $publicProjection The ProjectionView::Public payload.
     * @param string $html             The rendered HTML of the same page.
     * @return list<array{address:?string,key:string,text:string}> Missing facts.
     */
    public function check(array $publicProjection, string $html): array
    {
        // Two haystacks: visible prose (tags stripped) catches text nodes;
        // the raw decoded HTML catches facts that live in attributes (e.g. an
        // image alt). A fact present in EITHER satisfies parity.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $textHay = $this->normalize($this->htmlToText($html));
        $rawHay = $this->normalize($decoded);
        $missing = [];

        foreach ($publicProjection['schema_org']['@graph'] ?? [] as $node) {
            $address = is_array($node) ? ($node['stillopress:blockAddress'] ?? null) : null;

            foreach ((array) $node as $key => $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }
                if (in_array($key, self::NON_TEXT_KEYS, true) || str_starts_with($key, 'stillopress:')) {
                    continue;
                }

                $needle = $this->normalize($value);
                if ($needle === '') {
                    continue;
                }
                if (! str_contains($textHay, $needle) && ! str_contains($rawHay, $needle)) {
                    $missing[] = ['address' => $address, 'key' => $key, 'text' => $value];
                }
            }
        }

        return $missing;
    }

    private function htmlToText(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalize(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($s)));
    }
}
