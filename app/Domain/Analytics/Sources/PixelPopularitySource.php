<?php

namespace App\Domain\Analytics\Sources;

use App\Domain\Analytics\Contracts\PopularitySource;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-hosted source: reads the first-party `page_views` pixel (every published
 * page fires navigator.sendBeacon to /api/v1/sites/{id}/t). No external
 * credentials, no cookie banner. When the `page_engagements` table is present
 * (dwell time + read %), ranking is weighted by genuine reading rather than raw
 * loads — a fully-read post outranks a bounced one.
 */
class PixelPopularitySource implements PopularitySource
{
    public function key(): string
    {
        return 'pixel';
    }

    public function isConfigured(Site $site): bool
    {
        return true; // always available — it's our own pixel
    }

    public function topPaths(Site $site, int $windowDays, int $limit): array
    {
        $since = now()->subDays(max(1, $windowDays));

        // Engagement-weighted when the beacon reports dwell/read; otherwise a
        // plain view count. Weight blends reach (views) with attention
        // (avg dwell seconds × avg read fraction).
        if (Schema::hasTable('page_engagements')) {
            $rows = DB::table('page_views as v')
                ->leftJoin('page_engagements as e', function ($j) use ($since) {
                    $j->on('e.site_id', '=', 'v.site_id')
                      ->on('e.path', '=', 'v.path')
                      ->where('e.recorded_at', '>=', $since);
                })
                ->where('v.site_id', $site->id)
                ->where('v.viewed_at', '>=', $since)
                ->groupBy('v.path')
                ->select('v.path', DB::raw(
                    'ROUND(COUNT(v.id) * (1 + COALESCE(AVG(e.dwell_seconds),0)/60.0) * (0.4 + 0.6*COALESCE(AVG(e.read_pct),0)/100.0)) as score'
                ))
                ->orderByDesc('score')
                ->limit($limit)
                ->get();
        } else {
            $rows = DB::table('page_views')
                ->where('site_id', $site->id)
                ->where('viewed_at', '>=', $since)
                ->groupBy('path')
                ->select('path', DB::raw('COUNT(*) as score'))
                ->orderByDesc('score')
                ->limit($limit)
                ->get();
        }

        return $rows->map(fn ($r) => ['path' => (string) $r->path, 'score' => (int) $r->score])->all();
    }
}
