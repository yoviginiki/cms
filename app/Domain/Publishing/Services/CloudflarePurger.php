<?php

namespace App\Domain\Publishing\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Http;

/**
 * Purges the Cloudflare edge cache after a deploy goes live, so visitors see
 * the new build immediately instead of waiting out the edge TTL (~2h).
 *
 * Inert until CLOUDFLARE_API_TOKEN + CLOUDFLARE_ZONE_ID are set in .env.
 * The token needs only the "Zone → Cache Purge → Purge" permission.
 *
 * Uses purge_everything (one API call, every Cloudflare plan). Per-URL purge
 * only reaches page HTML — which is served DYNAMIC (uncached) here — and never
 * touched the ASSETS (webp/css/js/fonts) that actually sit in the edge cache
 * with stable, content-hashed names. purge_everything clears the whole zone so
 * a deploy (recompressed image, changed cache header, etc.) is visible at once.
 * Zone-wide is fine for this low-traffic, single-zone setup. Failures are
 * logged and never fail the deploy — a stale cache beats a red deployment.
 */
class CloudflarePurger
{
    public static function configured(): bool
    {
        return (string) config('cms.cloudflare.api_token') !== ''
            && (string) config('cms.cloudflare.zone_id') !== '';
    }

    /** Purge the whole zone edge cache after a deploy. Returns 1 if purged, else 0. */
    public static function purgeSite(Site $site, string $buildPath): int
    {
        // Guard on a real build so a failed/partial deploy never purges.
        if (!self::configured() || !is_dir($buildPath)) {
            return 0;
        }

        try {
            $response = Http::withToken((string) config('cms.cloudflare.api_token'))
                ->timeout(15)
                ->post(
                    'https://api.cloudflare.com/client/v4/zones/' . config('cms.cloudflare.zone_id') . '/purge_cache',
                    ['purge_everything' => true]
                );
            if ($response->successful() && ($response->json('success') === true)) {
                return 1;
            }
            logger()->warning('Cloudflare purge_everything failed', [
                'site' => $site->id,
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);
        } catch (\Throwable $e) {
            logger()->warning("Cloudflare purge error for site {$site->id}: {$e->getMessage()}");
        }

        return 0;
    }
}
