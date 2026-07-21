<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Search analytics (v3): anonymous per-day term counters. Accepts JSON or
 * text/plain (sendBeacon's no-preflight content type). Stores the lowercased
 * term only — no IP, no user agent, no session.
 */
class SearchBeaconController extends Controller
{
    public function store(Request $request, Site $site): JsonResponse
    {
        if (($site->settings['search_analytics'] ?? true) === false) {
            return response()->json(['ok' => false], 204);
        }

        $payload = $request->isJson() ? $request->all() : (json_decode($request->getContent(), true) ?: []);
        $term = mb_strtolower(trim(strip_tags((string) ($payload['q'] ?? ''))));
        if ($term === '' || mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return response()->json(['ok' => false], 204);
        }

        $now = now();
        DB::table('search_terms')->upsert(
            [[
                'id' => Str::uuid()->toString(),
                'site_id' => $site->id,
                'term' => $term,
                'day' => $now->toDateString(),
                'count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['site_id', 'term', 'day'],
            ['count' => DB::raw('search_terms.count + 1'), 'updated_at' => $now],
        );

        return response()->json(['ok' => true]);
    }

    /** Admin: top terms over the last N days. */
    public function top(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $days = max(1, min(365, (int) $request->query('days', 30)));

        $rows = DB::table('search_terms')
            ->where('site_id', $site->id)
            ->where('day', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('term, sum(count) AS total, max(day) AS last_seen')
            ->groupBy('term')
            ->orderByDesc('total')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'days' => $days,
            'terms' => $rows->map(fn ($r) => ['term' => $r->term, 'count' => (int) $r->total, 'last_seen' => $r->last_seen]),
        ]]);
    }
}
