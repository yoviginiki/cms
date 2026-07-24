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
 * Purges by explicit URL (works on every Cloudflare plan; prefix purge is
 * enterprise-only): every index.html in the finished build becomes its public
 * URL, batched at the API's 30-URLs-per-call limit. Failures are logged and
 * never fail the deploy — a stale cache beats a red deployment.
 */
class CloudflarePurger
{
    private const BATCH = 30;

    public static function configured(): bool
    {
        return (string) config('cms.cloudflare.api_token') !== ''
            && (string) config('cms.cloudflare.zone_id') !== '';
    }

    /** Purge all page URLs of a site's freshly deployed build. Returns purged-URL count. */
    public static function purgeSite(Site $site, string $buildPath): int
    {
        if (!self::configured() || !is_dir($buildPath)) {
            return 0;
        }

        $base = rtrim($site->publicBaseUrl(), '/');
        $urls = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($buildPath, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() !== 'index.html') {
                continue;
            }
            $rel = substr($file->getPath(), strlen(rtrim($buildPath, '/')));
            $urls[] = $base . ($rel === '' ? '/' : rtrim($rel, '/') . '/');
        }
        // Non-page assets that change with builds and are commonly cached.
        foreach (['/sitemap.xml', '/feed.xml', '/404.html'] as $extra) {
            if (file_exists(rtrim($buildPath, '/') . $extra)) {
                $urls[] = $base . $extra;
            }
        }

        $purged = 0;
        foreach (array_chunk(array_values(array_unique($urls)), self::BATCH) as $chunk) {
            try {
                $response = Http::withToken((string) config('cms.cloudflare.api_token'))
                    ->timeout(15)
                    ->post(
                        'https://api.cloudflare.com/client/v4/zones/' . config('cms.cloudflare.zone_id') . '/purge_cache',
                        ['files' => $chunk]
                    );
                if ($response->successful() && ($response->json('success') === true)) {
                    $purged += count($chunk);
                } else {
                    logger()->warning('Cloudflare purge batch failed', [
                        'site' => $site->id,
                        'status' => $response->status(),
                        'errors' => $response->json('errors'),
                    ]);
                }
            } catch (\Throwable $e) {
                logger()->warning("Cloudflare purge error for site {$site->id}: {$e->getMessage()}");
            }
        }

        return $purged;
    }
}
