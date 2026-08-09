<?php

namespace App\Domain\Analytics\Sources;

use App\Domain\Analytics\Contracts\PopularitySource;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Web Analytics source (privacy-first RUM, no cookie banner). Pulls
 * top paths over the window via Cloudflare's GraphQL Analytics API. Requires
 * settings.popularity.cloudflare = { api_token, zone_tag }. The CF Web
 * Analytics beacon must be enabled on the pages (injected at publish).
 */
class CloudflarePopularitySource implements PopularitySource
{
    public function key(): string
    {
        return 'cloudflare';
    }

    private function config(Site $site): array
    {
        return (array) ($site->settings['popularity']['cloudflare'] ?? []);
    }

    public function isConfigured(Site $site): bool
    {
        $c = $this->config($site);
        return !empty($c['api_token']) && !empty($c['zone_tag']);
    }

    public function topPaths(Site $site, int $windowDays, int $limit): array
    {
        if (!$this->isConfigured($site)) {
            return [];
        }
        $c = $this->config($site);
        $since = now()->subDays(max(1, $windowDays))->toDateString();

        $query = <<<'GQL'
        query($zone: String!, $since: Date!, $limit: Int!) {
          viewer { zones(filter: {zoneTag: $zone}) {
            rumPageloadEventsAdaptiveGroups(
              filter: {date_geq: $since}, limit: $limit,
              orderBy: [count_DESC]
            ) { count dimensions { requestPath } }
          } }
        }
        GQL;

        try {
            $res = Http::withToken($c['api_token'])
                ->timeout(20)
                ->post('https://api.cloudflare.com/client/v4/graphql', [
                    'query' => $query,
                    'variables' => ['zone' => $c['zone_tag'], 'since' => $since, 'limit' => $limit],
                ]);
            $groups = data_get($res->json(), 'data.viewer.zones.0.rumPageloadEventsAdaptiveGroups', []);
            return collect($groups)
                ->map(fn ($g) => ['path' => (string) data_get($g, 'dimensions.requestPath', ''), 'score' => (int) data_get($g, 'count', 0)])
                ->filter(fn ($r) => $r['path'] !== '')
                ->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Cloudflare popularity fetch failed', ['site' => $site->id, 'error' => $e->getMessage()]);
            return [];
        }
    }
}
