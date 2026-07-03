<?php

namespace App\Domain\References;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

/**
 * Site-scoped context for reference extraction: resolves URL strings found in
 * block data to internal entities (assets, pages, posts). External URLs
 * resolve to null and produce no edge.
 */
class ExtractionContext
{
    /** @var array<string, string>|null slug => id */
    private ?array $pageSlugMap = null;

    /** @var array<string, string>|null slug => id */
    private ?array $postSlugMap = null;

    public function __construct(public readonly Site $site)
    {
    }

    /**
     * Resolve a URL from block data to an edge, or null if external/unresolvable.
     *
     * @return array{target_type: string, target_id: ?string, kind: string}|null
     */
    public function resolveUrl(mixed $url): ?array
    {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }
        $url = trim($url);

        // Non-navigational or unsafe schemes
        if (preg_match('#^(mailto:|tel:|\#|javascript:|data:|vbscript:)#i', $url)) {
            return null;
        }

        // CMS asset serve URL: /api/v1/sites/{site}/assets/{asset}/serve[...]
        if (preg_match('#/api/v1/sites/[0-9a-f-]{36}/assets/([0-9a-f-]{36})/serve#i', $url, $m)) {
            return ['target_type' => 'asset', 'target_id' => strtolower($m[1]), 'kind' => 'uses_asset'];
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        // Absolute URL to another host = external, no edge
        if (!empty($parts['host']) && !$this->isOwnHost($parts['host'])) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');
        if ($path === '') {
            return null; // homepage — rebuilt on every publish anyway
        }
        if (str_starts_with($path, 'api/') || str_starts_with($path, 'assets/')) {
            return null;
        }

        // Blog post via blog index path: blog/{slug}
        if (preg_match('#^blog/([^/]+)#', $path, $m)) {
            $postId = $this->postSlugMap()[$m[1]] ?? null;

            return $postId ? ['target_type' => 'post', 'target_id' => $postId, 'kind' => 'links'] : null;
        }

        $segments = explode('/', $path);

        // Page: published page paths are flat ({slug}/index.html), first segment = slug
        if (count($segments) === 1) {
            $pageId = $this->pageSlugMap()[$segments[0]] ?? null;

            return $pageId ? ['target_type' => 'page', 'target_id' => $pageId, 'kind' => 'links'] : null;
        }

        // Post: published post paths are {categorySlug}/{postSlug}/index.html
        $postId = $this->postSlugMap()[$segments[1]] ?? null;

        return $postId ? ['target_type' => 'post', 'target_id' => $postId, 'kind' => 'links'] : null;
    }

    private function isOwnHost(string $host): bool
    {
        $host = strtolower($host);
        $own = array_filter([
            $this->site->custom_domain ? strtolower($this->site->custom_domain) : null,
            strtolower("{$this->site->slug}.ensodo.eu"),
        ]);

        return in_array($host, $own, true);
    }

    private function pageSlugMap(): array
    {
        return $this->pageSlugMap ??= Page::where('site_id', $this->site->id)
            ->pluck('id', 'slug')
            ->all();
    }

    private function postSlugMap(): array
    {
        return $this->postSlugMap ??= Post::where('site_id', $this->site->id)
            ->pluck('id', 'slug')
            ->all();
    }
}
