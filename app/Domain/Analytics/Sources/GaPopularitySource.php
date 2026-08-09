<?php

namespace App\Domain\Analytics\Sources;

use App\Domain\Analytics\Contracts\PopularitySource;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Analytics 4 source. Uses GA4's own engagement signal
 * (userEngagementDuration) blended with pageviews, so "most read" reflects
 * attention, not just clicks. Requires settings.popularity.ga = {
 * property_id, service_account } where service_account is the JSON key. Auth is
 * a service-account JWT (RS256) exchanged for an access token — no external
 * library needed.
 */
class GaPopularitySource implements PopularitySource
{
    public function key(): string
    {
        return 'ga';
    }

    private function config(Site $site): array
    {
        return (array) ($site->settings['popularity']['ga'] ?? []);
    }

    public function isConfigured(Site $site): bool
    {
        $c = $this->config($site);
        return !empty($c['property_id']) && !empty($c['service_account']['client_email']) && !empty($c['service_account']['private_key']);
    }

    public function topPaths(Site $site, int $windowDays, int $limit): array
    {
        if (!$this->isConfigured($site)) {
            return [];
        }
        $c = $this->config($site);
        try {
            $token = $this->accessToken($c['service_account']);
            if (!$token) {
                return [];
            }
            $res = Http::withToken($token)->timeout(20)->post(
                "https://analyticsdata.googleapis.com/v1beta/properties/{$c['property_id']}:runReport",
                [
                    'dateRanges' => [['startDate' => max(1, $windowDays) . 'daysAgo', 'endDate' => 'today']],
                    'dimensions' => [['name' => 'pagePath']],
                    'metrics' => [['name' => 'screenPageViews'], ['name' => 'userEngagementDuration']],
                    'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                    'limit' => $limit,
                ]
            );
            return collect($res->json('rows', []))
                ->map(function ($row) {
                    $path = (string) data_get($row, 'dimensionValues.0.value', '');
                    $views = (int) data_get($row, 'metricValues.0.value', 0);
                    $engage = (float) data_get($row, 'metricValues.1.value', 0); // seconds, total
                    // Blend reach with attention (avg engaged seconds per view).
                    $avgEngage = $views > 0 ? $engage / $views : 0;
                    return ['path' => $path, 'score' => (int) round($views * (1 + $avgEngage / 60))];
                })
                ->filter(fn ($r) => $r['path'] !== '')
                ->values()->all();
        } catch (\Throwable $e) {
            Log::warning('GA popularity fetch failed', ['site' => $site->id, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /** Service-account JWT → OAuth access token (RS256 via openssl). */
    private function accessToken(array $sa): ?string
    {
        $now = time();
        $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $b64(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now, 'exp' => $now + 3600,
        ]));
        $sig = '';
        if (!openssl_sign("{$header}.{$claim}", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = "{$header}.{$claim}." . $b64($sig);
        $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        return $res->json('access_token');
    }
}
