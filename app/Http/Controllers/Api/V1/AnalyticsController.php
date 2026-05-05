<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Public tracking pixel — called from published pages via JS.
     * No auth required. Rate-limited.
     */
    public function track(Request $request, Site $site): JsonResponse
    {
        $path = $request->input('p', '/');
        $referrer = $request->input('r');

        // Parse user agent
        $ua = $request->userAgent() ?? '';
        $device = 'desktop';
        if (preg_match('/Mobile|Android|iPhone/i', $ua)) $device = 'mobile';
        elseif (preg_match('/Tablet|iPad/i', $ua)) $device = 'tablet';

        $browser = 'other';
        if (str_contains($ua, 'Chrome')) $browser = 'chrome';
        elseif (str_contains($ua, 'Firefox')) $browser = 'firefox';
        elseif (str_contains($ua, 'Safari')) $browser = 'safari';
        elseif (str_contains($ua, 'Edge')) $browser = 'edge';

        DB::table('page_views')->insert([
            'site_id' => $site->id,
            'path' => substr($path, 0, 500),
            'referrer' => $referrer ? substr($referrer, 0, 500) : null,
            'device' => $device,
            'browser' => $browser,
            'viewed_at' => now(),
        ]);

        // Return 1x1 transparent pixel
        return response()->json(['ok' => true]);
    }

    /**
     * Dashboard stats — admin only.
     */
    public function dashboard(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $days = $request->integer('days', 30);
        $since = now()->subDays($days);

        // Total views
        $totalViews = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->count();

        // Views per day
        $viewsPerDay = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->selectRaw("DATE(viewed_at) as date, COUNT(*) as views")
            ->groupByRaw('DATE(viewed_at)')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'views' => $r->views]);

        // Top pages
        $topPages = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        // Top referrers
        $topReferrers = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->select('referrer', DB::raw('COUNT(*) as views'))
            ->groupBy('referrer')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Device breakdown
        $devices = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->select('device', DB::raw('COUNT(*) as views'))
            ->groupBy('device')
            ->get()
            ->pluck('views', 'device');

        // Browser breakdown
        $browsers = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', $since)
            ->select('browser', DB::raw('COUNT(*) as views'))
            ->groupBy('browser')
            ->get()
            ->pluck('views', 'browser');

        // Today vs yesterday
        $today = DB::table('page_views')
            ->where('site_id', $site->id)
            ->whereDate('viewed_at', today())
            ->count();
        $yesterday = DB::table('page_views')
            ->where('site_id', $site->id)
            ->whereDate('viewed_at', today()->subDay())
            ->count();

        return response()->json(['data' => [
            'period_days' => $days,
            'total_views' => $totalViews,
            'today' => $today,
            'yesterday' => $yesterday,
            'change_pct' => $yesterday > 0 ? round(($today - $yesterday) / $yesterday * 100) : 0,
            'views_per_day' => $viewsPerDay,
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
            'devices' => $devices,
            'browsers' => $browsers,
        ]]);
    }

    /**
     * Get most viewed posts — used by the "popular posts" widget.
     */
    public function popularPosts(Site $site, int $limit = 10): array
    {
        $paths = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->where('path', 'like', '/blog/%')
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->pluck('views', 'path');

        return $paths->toArray();
    }
}
