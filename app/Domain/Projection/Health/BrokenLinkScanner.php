<?php

namespace App\Domain\Projection\Health;

/**
 * Site Health Ledger (first slice): broken internal-link detection.
 *
 * Pure — consumes the inventory the projection already produces, so the scan is
 * trivial: an internal outbound link is broken when its target is not among the
 * site's known page/post URLs. No I/O, deterministic.
 */
class BrokenLinkScanner
{
    /**
     * @param array<string,array> $projectionsByUrl  canonical page URL => projection array (Projection::toArray)
     * @return list<array{source:string,target:string,address:string}> broken links, in source order
     */
    public function scan(array $projectionsByUrl): array
    {
        $valid = [];
        foreach (array_keys($projectionsByUrl) as $url) {
            $valid[$this->normalize($url)] = true;
        }

        $broken = [];
        foreach ($projectionsByUrl as $sourceUrl => $projection) {
            foreach ($projection['inventory']['outbound_links'] ?? [] as $link) {
                if (empty($link['internal'])) {
                    continue; // external links are out of scope for this scan
                }
                $target = $this->normalize((string) $link['url']);
                if ($target === '' || $target === $this->normalize($sourceUrl)) {
                    continue; // same-page anchors / empty targets are not broken
                }
                if (! isset($valid[$target])) {
                    $broken[] = [
                        'source' => $sourceUrl,
                        'target' => $link['url'],
                        'address' => $link['address'],
                    ];
                }
            }
        }

        return $broken;
    }

    /** Canonicalise a path: drop fragment/query, force a single leading+trailing slash. */
    private function normalize(string $url): string
    {
        $url = preg_replace('/[?#].*$/', '', $url) ?? '';
        $url = trim($url);
        if ($url === '' || $url === '/') {
            return $url === '' ? '' : '/';
        }

        return '/' . trim($url, '/') . '/';
    }
}
