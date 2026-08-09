<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Contracts\PopularitySource;
use App\Domain\Analytics\Sources\CloudflarePopularitySource;
use App\Domain\Analytics\Sources\GaPopularitySource;
use App\Domain\Analytics\Sources\PixelPopularitySource;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Computes "most read" popularity for a site from its configured source and
 * writes a rolling signal onto posts (seo_meta.win_views + popular_rank), which
 * the latestposts block reads via orderBy=popular. Then republishes so the
 * static homepage reflects the new ranking.
 *
 * settings.popularity = {
 *   enabled: bool, source: 'pixel'|'ga'|'cloudflare',
 *   cadence: 'daily'|'weekly', window_days: int, count: int, last_run: iso
 * }
 */
class PopularityService
{
    /** @return array<string, PopularitySource> keyed by source key */
    public function sources(): array
    {
        return collect([
            app(PixelPopularitySource::class),
            app(GaPopularitySource::class),
            app(CloudflarePopularitySource::class),
        ])->keyBy(fn (PopularitySource $s) => $s->key())->all();
    }

    public function config(Site $site): array
    {
        return array_merge([
            'enabled' => false,
            'source' => 'pixel',
            'cadence' => 'daily',
            'window_days' => 7,
            'count' => 12,
        ], (array) ($site->settings['popularity'] ?? []));
    }

    /** Whether the site is due for a refresh given its cadence + last_run. */
    public function isDue(Site $site): bool
    {
        $cfg = $this->config($site);
        if (!$cfg['enabled']) {
            return false;
        }
        $last = $cfg['last_run'] ?? null;
        if (!$last) {
            return true;
        }
        $next = \Illuminate\Support\Carbon::parse($last)
            ->add($cfg['cadence'] === 'weekly' ? '1 week' : '1 day');

        return now()->greaterThanOrEqualTo($next);
    }

    /**
     * Refresh popularity for one site. Returns a summary array.
     */
    public function refresh(Site $site, bool $publish = true): array
    {
        $cfg = $this->config($site);
        $source = $this->sources()[$cfg['source']] ?? null;
        if (!$source) {
            return ['ok' => false, 'reason' => "unknown source '{$cfg['source']}'"];
        }
        if (!$source->isConfigured($site)) {
            return ['ok' => false, 'reason' => "source '{$cfg['source']}' not configured"];
        }

        $count = max(1, (int) $cfg['count']);
        $window = max(1, (int) $cfg['window_days']);
        // Over-fetch so unmapped paths (archives, homepage, deleted posts) don't
        // starve the list.
        $ranked = $source->topPaths($site, $window, $count * 4);

        $newIds = [];
        $rank = 0;
        foreach ($ranked as $row) {
            $post = $this->resolvePost($site, $row['path']);
            if (!$post || in_array($post->id, $newIds, true)) {
                continue;
            }
            $sm = $post->seo_meta ?? [];
            $sm['win_views'] = (int) $row['score'];
            $sm['popular_rank'] = $rank + 1;
            $post->update(['seo_meta' => $sm]);
            $newIds[] = $post->id;
            if (++$rank >= $count) {
                break;
            }
        }

        // Clear the previous window's signal from posts no longer in the list.
        // jsonb_exists(), not the `?` operator — PDO reads `?` as a bind param.
        $stale = Post::where('site_id', $site->id)
            ->when($newIds, fn ($q) => $q->whereNotIn('id', $newIds))
            ->whereRaw("jsonb_exists(seo_meta, 'win_views')")->get();
        foreach ($stale as $p) {
            $sm = $p->seo_meta ?? [];
            unset($sm['win_views'], $sm['popular_rank']);
            $p->update(['seo_meta' => $sm]);
        }

        // Record the run.
        $settings = $site->settings ?? [];
        $settings['popularity'] = array_merge($settings['popularity'] ?? [], ['last_run' => now()->toIso8601String()]);
        $site->update(['settings' => $settings]);

        // Republish so the static "Most read" reflects the new ranking. Partial
        // publish + a stale homepage keeps this cheap (no full 7k-page rebuild).
        if ($publish && ($ranked !== [] || $stale->isNotEmpty())) {
            $this->republishHome($site);
        }

        return ['ok' => true, 'ranked' => count($newIds), 'cleared' => $stale->count(), 'source' => $cfg['source']];
    }

    /** Map a tracked path (e.g. /artday/some-slug or /some-slug) to a post. */
    private function resolvePost(Site $site, string $path): ?Post
    {
        $segs = array_values(array_filter(explode('/', trim(parse_url($path, PHP_URL_PATH) ?: $path, '/'))));
        if (!$segs) {
            return null;
        }
        $slug = rawurldecode(end($segs));

        return Post::where('site_id', $site->id)->where('slug', $slug)->where('status', 'published')->first();
    }

    private function republishHome(Site $site): void
    {
        try {
            // Touch the homepage so a partial publish re-renders it (its
            // latestposts block re-reads the new popularity signal).
            $homeId = $site->settings['homepage_id'] ?? null;
            $home = $homeId ? \App\Models\Page::find($homeId) : null;
            $home?->forceFill(['content_modified_at' => now()])->save();

            $user = User::query()->first();
            if ($user) {
                app(PublishOrchestrator::class)->publish($site, $user, 'partial');
            }
        } catch (\Throwable $e) {
            Log::warning('Popularity republish failed', ['site' => $site->id, 'error' => $e->getMessage()]);
        }
    }
}
