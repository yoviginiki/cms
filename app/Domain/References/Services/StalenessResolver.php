<?php

namespace App\Domain\References\Services;

use App\Models\EntityReference;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

/**
 * Given a changed target entity, walks entity_references INVERSE edges
 * transitively (asset → slider → pages) and flags the affected pages/posts
 * with needs_republish + a human-readable reason.
 *
 * Site-scope impact (theme, located menu) sets ONE flag on the site's
 * settings — expanded lazily when the stale list is read, never written
 * per-page.
 *
 * Flags are advisory: nothing publishes automatically. The full publish
 * pipeline clears them on success (a full rebuild covers everything).
 */
class StalenessResolver
{
    private const MAX_DEPTH = 5;

    /**
     * Edge kinds meaning "the source's rendered output CONTAINS the target's
     * content" — these propagate content changes. `links` is deliberately
     * excluded: a link only goes stale when its target's URL changes
     * (see markStaleForLinkTargets), not when the target's content does.
     * `uses` = the source's rendered CSS depends on a style preset (P3).
     */
    private const CONTENT_KINDS = ['embeds', 'uses_asset', 'lists', 'site_scope', 'uses'];

    /**
     * A target entity's CONTENT changed. Returns summary counts.
     *
     * @return array{pages: int, posts: int, site_wide: bool}
     */
    public function markStale(Site $site, string $targetType, string $targetId, string $reason): array
    {
        $pageIds = [];
        $postIds = [];
        $siteWide = false;

        $queue = [[$targetType, $targetId]];
        $visited = ["{$targetType}|{$targetId}" => true];
        $depth = 0;

        while ($queue !== [] && $depth < self::MAX_DEPTH) {
            $nextQueue = [];

            foreach ($queue as [$type, $id]) {
                $edges = EntityReference::forTarget($site->id, $type, $id)
                    ->whereIn('kind', self::CONTENT_KINDS)
                    ->get(['source_type', 'source_id']);

                foreach ($edges as $edge) {
                    $key = "{$edge->source_type}|{$edge->source_id}";
                    if (isset($visited[$key])) {
                        continue;
                    }
                    $visited[$key] = true;

                    match ($edge->source_type) {
                        'page' => $pageIds[] = $edge->source_id,
                        'post' => $postIds[] = $edge->source_id,
                        // theme/located-menu changes and template-mediated
                        // content both affect every page of the site
                        'site', 'template' => $siteWide = true,
                        // any other source (slider, magazine_doc, ...) is an
                        // intermediate entity — keep walking
                        default => $nextQueue[] = [$edge->source_type, $edge->source_id],
                    };
                }
            }

            $queue = $nextQueue;
            $depth++;
        }

        if ($queue !== []) {
            Log::warning("StalenessResolver: depth limit (" . self::MAX_DEPTH . ") hit walking {$targetType}:{$targetId} on site {$site->id}; remaining nodes not expanded.");
        }

        $this->flagPages($pageIds, $reason);
        $this->flagPosts($postIds, $reason);
        if ($siteWide) {
            $this->markSiteStale($site, $reason);
        }
        if ($pageIds !== [] || $postIds !== []) {
            $this->maybeAutoRepublish($site, $reason);
        }

        return ['pages' => count($pageIds), 'posts' => count($postIds), 'site_wide' => $siteWide];
    }

    /**
     * A page/post URL changed (slug rename) or the target was deleted:
     * every source with an inbound `links` edge contains the old URL.
     * Not transitive — a stale link does not propagate.
     *
     * Stored hrefs are NOT rewritten (they live in free-form HTML inside
     * block jsonb; rewriting risks corrupting content) — referrers are
     * flagged for review/republish instead.
     *
     * @return array{pages: int, posts: int}
     */
    public function markStaleForLinkTargets(Site $site, string $targetType, string $targetId, string $reason): array
    {
        $edges = EntityReference::where('site_id', $site->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('kind', 'links')
            ->get(['source_type', 'source_id']);

        $pageIds = $edges->where('source_type', 'page')->pluck('source_id')->all();
        $postIds = $edges->where('source_type', 'post')->pluck('source_id')->all();

        $this->flagPages($pageIds, $reason);
        $this->flagPosts($postIds, $reason);
        if ($pageIds !== [] || $postIds !== []) {
            $this->maybeAutoRepublish($site, $reason);
        }

        return ['pages' => count($pageIds), 'posts' => count($postIds)];
    }

    /**
     * Auto-republish toggle (default OFF): every staleness source — current
     * and future (slider publish) — funnels through here for free.
     */
    private function maybeAutoRepublish(Site $site, string $reason): void
    {
        try {
            app(StaleAutoRepublisher::class)->maybeQueue($site, $reason);
        } catch (\Throwable $e) {
            Log::warning("Auto-republish queue failed for site {$site->id}: {$e->getMessage()}");
        }
    }

    /**
     * A post was created/updated/deleted: listing pages (its category's,
     * and unfiltered "latest posts" wildcards) plus postcards embedding it.
     *
     * @return array{pages: int, posts: int, site_wide: bool}
     */
    public function resolveForPostChange(Site $site, Post $post, string $reason): array
    {
        // covers embeds of this post AND wildcard "lists any post" edges
        $result = $this->markStale($site, 'post', $post->id, $reason);

        if ($post->category_id) {
            $categoryResult = $this->markStale($site, 'category', $post->category_id, $reason);
            $result['pages'] += $categoryResult['pages'];
            $result['posts'] += $categoryResult['posts'];
            $result['site_wide'] = $result['site_wide'] || $categoryResult['site_wide'];
        }

        return $result;
    }

    /**
     * Site-wide staleness: one flag on the site, expanded lazily on read.
     */
    public function markSiteStale(Site $site, string $reason): void
    {
        $settings = $site->settings ?? [];
        $settings['stale'] = ['flag' => true, 'reason' => $reason, 'at' => now()->toIso8601String()];
        $site->settings = $settings;
        $site->save();
    }

    /**
     * Clear needs_republish for the items a delta batch built, but ONLY where
     * the item hasn't been re-flagged since the build (its updated_at still
     * matches the captured build stamp). Closes the lost-update race (§7 D2):
     * if a dependency changed again after the build snapshot, the newer
     * staleness is preserved instead of being erased by a blanket clear.
     *
     * @param array<int,array{type:string,id:string,stamp?:?string}> $built
     */
    public function clearBuiltIfUnchanged(array $built): void
    {
        foreach ($built as $item) {
            $model = ($item['type'] ?? null) === 'post'
                ? Post::find($item['id'] ?? null)
                : Page::find($item['id'] ?? null);
            if (!$model) {
                continue;
            }
            $currentStamp = optional($model->updated_at)->toIso8601String();
            if (($item['stamp'] ?? null) === $currentStamp) {
                $model->forceFill(['needs_republish' => false, 'needs_republish_reason' => null])->save();
            }
        }
    }

    /**
     * A successful FULL publish covers everything: clear all flags.
     */
    public function clearForSite(Site $site): void
    {
        Page::where('site_id', $site->id)->where('needs_republish', true)
            ->update(['needs_republish' => false, 'needs_republish_reason' => null]);
        Post::where('site_id', $site->id)->where('needs_republish', true)
            ->update(['needs_republish' => false, 'needs_republish_reason' => null]);

        $settings = $site->settings ?? [];
        if (isset($settings['stale'])) {
            unset($settings['stale']);
            $site->settings = $settings;
            $site->save();
        }
    }

    /**
     * A site-wide change with no entity_references edge to walk — the active
     * theme switched, so every published page/post carries stale inlined token
     * CSS and must be rebuilt. Flags all of them plus the site-wide marker.
     *
     * @return array{pages: int, posts: int, site_wide: bool}
     */
    public function markAllStale(Site $site, string $reason): array
    {
        $pages = Page::where('site_id', $site->id)
            ->where('status', 'published')
            ->update(['needs_republish' => true, 'needs_republish_reason' => $reason]);
        $posts = Post::where('site_id', $site->id)
            ->where('status', 'published')
            ->update(['needs_republish' => true, 'needs_republish_reason' => $reason]);

        $this->markSiteStale($site, $reason);
        if ($pages > 0 || $posts > 0) {
            $this->maybeAutoRepublish($site, $reason);
        }

        return ['pages' => $pages, 'posts' => $posts, 'site_wide' => true];
    }

    private function flagPages(array $ids, string $reason): void
    {
        if ($ids !== []) {
            Page::whereIn('id', array_unique($ids))
                ->update(['needs_republish' => true, 'needs_republish_reason' => $reason]);
        }
    }

    private function flagPosts(array $ids, string $reason): void
    {
        if ($ids !== []) {
            Post::whereIn('id', array_unique($ids))
                ->update(['needs_republish' => true, 'needs_republish_reason' => $reason]);
        }
    }
}
