<?php

namespace App\Domain\Analytics\Contracts;

use App\Models\Site;

/**
 * A source of "what's being read" data for a static site. Implementations pull
 * top page paths over a trailing window from wherever readership is measured
 * (our own pixel, Google Analytics, Cloudflare) so the popularity refresh can
 * rank posts uniformly regardless of provider.
 */
interface PopularitySource
{
    /** Machine key stored in settings.popularity.source. */
    public function key(): string;

    /** True when this source has everything it needs to run (credentials etc.). */
    public function isConfigured(Site $site): bool;

    /**
     * Top page paths over the last $windowDays, most-read first.
     *
     * @return array<int, array{path: string, score: int}> ranked desc by score
     */
    public function topPaths(Site $site, int $windowDays, int $limit): array;
}
