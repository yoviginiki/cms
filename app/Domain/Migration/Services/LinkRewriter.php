<?php

namespace App\Domain\Migration\Services;

use App\Domain\Publishing\Services\LocalePaths;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Support\Slugify;

/**
 * Recreates the origin site's internal links against the migrated content:
 * every href that pointed at the origin domain (or was site-relative) is
 * resolved through the slug map to the new canonical URL (pages: /{slug}/,
 * posts: /{category}/{slug}/). Cloudflare email-protection pseudo-links are
 * unwrapped to their text. Unknown targets are left untouched and reported.
 */
class LinkRewriter
{
    /** @var array<string, string> slug => canonical url path */
    private array $map = [];

    /** @var array<string, true> category slugs (archive URLs live at /{slug}/) */
    private array $categorySlugs = [];

    private array $unresolved = [];

    public function buildMap(Site $site): static
    {
        $this->map = [];
        foreach (Page::where('site_id', $site->id)->get() as $p) {
            $this->map[$p->slug] = LocalePaths::urlPath($site, $p);
        }
        foreach (Post::where('site_id', $site->id)->with('category')->get() as $p) {
            $this->map[$p->slug] = LocalePaths::urlPath($site, $p);
        }
        $this->categorySlugs = \App\Models\Category::where('site_id', $site->id)
            ->pluck('slug')->flip()->map(fn () => true)->all();

        return $this;
    }

    /** @return array<string, string> */
    public function map(): array
    {
        return $this->map;
    }

    /** @return string[] origin paths that had no migrated target */
    public function unresolved(): array
    {
        return array_values(array_unique($this->unresolved));
    }

    /** Resolve one origin path (decoded or encoded) to a new URL, or null. */
    public function resolvePath(string $path): ?string
    {
        $trimmed = trim(urldecode(strtok($path, '?') ?: ''), '/');
        if ($trimmed === '') {
            return '/';
        }

        // already-canonical URL (a previous rewrite, or a category-prefixed
        // post path) — keep as-is instead of re-slugifying the whole path
        $canonical = '/' . $trimmed . '/';
        if (in_array($canonical, $this->map, true)) {
            return $canonical;
        }

        $slug = Slugify::slug($trimmed);
        if (isset($this->map[$slug])) {
            return $this->map[$slug];
        }

        // category-archive URLs (/category/x/, /story/parent/x/) — the last
        // segment is the category slug and our archives live at /{slug}/
        $segments = explode('/', $trimmed);
        $last = Slugify::slug((string) end($segments));
        if ($last !== '' && isset($this->categorySlugs[$last])) {
            return "/{$last}/";
        }

        return null;
    }

    /** Rewrite every internal link in an HTML fragment. */
    public function rewriteHtml(string $html, string $originHost): string
    {
        $html = preg_replace(
            '#<a[^>]+href="[^"]*cdn-cgi/l/email-protection[^"]*"[^>]*>(.*?)</a>#is',
            '$1',
            $html
        );

        return preg_replace_callback('#href="([^"]+)"#i', function ($m) use ($originHost) {
            $href = $m[1];
            if (preg_match('#^(tel:|mailto:|\#|/api/)#i', $href)) {
                return $m[0];
            }

            $path = null;
            $host = preg_quote(preg_replace('/^www\./', '', $originHost), '#');
            if (preg_match("#^https?://(www\\.)?{$host}(/[^\"]*)?$#i", $href, $mm)) {
                $path = $mm[2] ?? '/';
            } elseif (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                $path = $href;
            }
            if ($path === null) {
                return $m[0]; // external
            }

            $new = $this->resolvePath($path);
            if ($new === null) {
                $this->unresolved[] = $path;

                return $m[0];
            }

            return 'href="' . $new . '"';
        }, $html);
    }

    /** Rewrite links inside every content block of the site. @return int blocks changed */
    public function rewriteSiteBlocks(Site $site, string $originHost): int
    {
        $ids = array_merge(
            Page::where('site_id', $site->id)->pluck('id')->all(),
            Post::where('site_id', $site->id)->pluck('id')->all(),
        );

        $changed = 0;
        foreach (Block::whereIn('blockable_id', $ids)->get() as $block) {
            $data = $block->data;
            $dirty = false;
            foreach (['content', 'text', 'url'] as $key) {
                if (empty($data[$key]) || !is_string($data[$key])) {
                    continue;
                }
                if ($key === 'url') {
                    $new = preg_replace('/^href="|"$/', '', $this->rewriteHtml('href="' . $data[$key] . '"', $originHost));
                } else {
                    $new = $this->rewriteHtml($data[$key], $originHost);
                }
                if ($new !== $data[$key]) {
                    $data[$key] = $new;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $block->update(['data' => $data]);
                $changed++;
            }
        }

        return $changed;
    }
}
