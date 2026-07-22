<?php

namespace App\Services\SiteWizard;

use App\Models\SiteWizard\SiteWizardSession;
use App\Support\SsrfGuard;
use Illuminate\Support\Str;

/**
 * URL-mode ingest: extract the entry page (manifest + nav + style signals) and
 * derive the crawl work list from its navigation and same-origin links. Only
 * the ENTRY page is loaded here — the remaining pages are extracted lazily by
 * the pages step (3 per job invocation), which also appends links it discovers
 * on depth-1 pages until the page cap is reached.
 */
class SiteCrawler
{
    public function __construct(private SitePageExtractor $extractor)
    {
    }

    /**
     * @return array{entry: array, sources: array<int, array>, nav: array, style: array}
     */
    public function ingest(SiteWizardSession $session): array
    {
        $entryUrl = $this->normalize($session->reference_url, $session->reference_url);
        $entry = $this->extractor->fromUrl($entryUrl);

        $maxPages = $this->maxPages($session);
        $sources = [[
            'ref' => $entryUrl,
            'slug' => 'home',
            'title' => $entry['manifest']['page_title'] ?? 'Home',
            'is_home' => true,
            'depth' => 0,
            'page_id' => null,
            'status' => 'pending',
            'error' => null,
        ]];

        // Nav links first (they define the site), then body links, both same-origin.
        $candidates = array_merge(
            array_map(fn ($n) => $n['href'] ?? '', $entry['nav']),
            $entry['links'],
        );
        foreach ($candidates as $href) {
            if (count($sources) >= $maxPages) {
                break;
            }
            $source = $this->toSource($href, $entryUrl, 1, array_column($sources, 'slug'));
            if ($source !== null && !in_array($source['ref'], array_column($sources, 'ref'), true)) {
                $sources[] = $source;
            }
        }

        return [
            'entry' => $entry,
            'sources' => $sources,
            'nav' => $entry['nav'],
            'style' => $entry['style'],
        ];
    }

    /**
     * Fold links discovered on a depth-1 page into the work list (depth 2),
     * respecting the page cap. Returns the possibly-extended list.
     */
    public function extendFrontier(SiteWizardSession $session, array $sources, array $links, int $fromDepth): array
    {
        if ($fromDepth >= 2) {
            return $sources;
        }
        $entryUrl = $sources[0]['ref'] ?? $session->reference_url;
        $maxPages = $this->maxPages($session);

        foreach ($links as $href) {
            if (count($sources) >= $maxPages) {
                break;
            }
            $source = $this->toSource($href, $entryUrl, $fromDepth + 1, array_column($sources, 'slug'));
            if ($source !== null && !in_array($source['ref'], array_column($sources, 'ref'), true)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    public function maxPages(SiteWizardSession $session): int
    {
        $cap = (int) config('cms.site_wizard.max_pages', 15);

        return max(1, min((int) ($session->options['max_pages'] ?? $cap), 20));
    }

    private function toSource(string $href, string $entryUrl, int $depth, array $takenSlugs): ?array
    {
        $url = $this->normalize($href, $entryUrl);
        if ($url === null) {
            return null;
        }
        try {
            SsrfGuard::assertPublicHttpUrl($url);
        } catch (\RuntimeException) {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $slug = $path === '/' ? 'home' : (Str::slug(str_replace('/', '-', trim($path, '/'))) ?: 'page');
        $base = $slug;
        $n = 2;
        while (in_array($slug, $takenSlugs, true)) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return [
            'ref' => $url,
            'slug' => $slug,
            'title' => null,
            'is_home' => false,
            'depth' => $depth,
            'page_id' => null,
            'status' => 'pending',
            'error' => null,
        ];
    }

    /** Same-origin page URL normalized for dedupe: no fragment/query, no trailing slash, no asset files. */
    private function normalize(?string $href, ?string $entryUrl): ?string
    {
        if (!is_string($href) || $href === '' || !is_string($entryUrl)) {
            return null;
        }
        $entry = parse_url($entryUrl);
        $parts = parse_url($href);
        if ($parts === false || empty($parts['host']) || empty($entry['host'])) {
            return null;
        }
        if (strcasecmp($parts['host'], $entry['host']) !== 0) {
            return null;
        }
        $scheme = $parts['scheme'] ?? $entry['scheme'] ?? 'https';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        // Skip obvious non-page targets.
        if (preg_match('/\.(png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|pdf|zip|mp4|webm|woff2?|ttf|otf)$/i', $path)) {
            return null;
        }
        $path = '/' . ltrim($path, '/');
        $path = $path === '/' ? '' : rtrim($path, '/');

        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return strtolower($scheme) . '://' . $parts['host'] . $port . $path;
    }
}
