<?php

namespace App\Domain\Migration\Services;

use Illuminate\Support\Facades\Http;

/**
 * Crawls an origin site's sitemaps into a URL inventory for migration.
 * WordPress (Yoast/core) exposes a sitemap index at /sitemap.xml (or
 * /sitemap_index.xml / /wp-sitemap.xml); child sitemap filenames tell us the
 * content type (post-sitemap, page-sitemap, category-sitemap, …).
 */
class OriginInventory
{
    /** @return array<int, array{url: string, type: string}> */
    public function collect(string $originBase): array
    {
        $originBase = rtrim($originBase, '/');
        $index = null;
        foreach (['sitemap.xml', 'sitemap_index.xml', 'wp-sitemap.xml'] as $candidate) {
            $xml = $this->fetchXml("{$originBase}/{$candidate}");
            if ($xml !== null) {
                $index = $xml;
                break;
            }
        }
        if ($index === null) {
            return [];
        }

        // A plain urlset (no index) — treat every URL as a page
        if ($index->getName() === 'urlset') {
            return $this->urlsFrom($index, 'page');
        }

        $inventory = [];
        foreach ($index->sitemap as $child) {
            $loc = (string) $child->loc;
            $type = $this->typeFromSitemapName($loc);
            if ($type === null) {
                continue; // attachments, authors — not content we rebuild
            }
            $urlset = $this->fetchXml($loc);
            if ($urlset === null) {
                continue;
            }
            $inventory = array_merge($inventory, $this->urlsFrom($urlset, $type));
        }

        return $inventory;
    }

    private function typeFromSitemapName(string $loc): ?string
    {
        $name = basename(parse_url($loc, PHP_URL_PATH) ?? '');

        return match (true) {
            str_contains($name, 'post-sitemap'), str_contains($name, 'posts-') => 'post',
            str_contains($name, 'page-sitemap'), str_contains($name, 'pages-') => 'page',
            str_contains($name, 'category') => 'category',
            default => null,
        };
    }

    /** @return array<int, array{url: string, type: string}> */
    private function urlsFrom(\SimpleXMLElement $urlset, string $type): array
    {
        $out = [];
        foreach ($urlset->url as $u) {
            $loc = trim((string) $u->loc);
            if ($loc !== '') {
                $out[] = ['url' => $loc, 'type' => $type];
            }
        }

        return $out;
    }

    private function fetchXml(string $url): ?\SimpleXMLElement
    {
        try {
            $res = Http::timeout(30)->get($url);
            if (!$res->successful()) {
                return null;
            }
            $xml = @simplexml_load_string($res->body());

            return $xml === false ? null : $xml;
        } catch (\Throwable) {
            return null;
        }
    }
}
