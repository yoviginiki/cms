<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Domain\Projection\Health\BrokenLinkScanner;
use App\Domain\Projection\ProjectionPublisher;
use App\Domain\Publishing\Services\LocalePaths;
use App\Models\SiteHealthReport;
use Illuminate\Console\Command;

/**
 * Site Health Ledger (first slice) — report broken internal links across a
 * site, derived from the projection inventory. Read-only.
 */
class ProjectionHealthCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'projection:health
        {site : site slug or id}
        {--strict : exit non-zero if any broken links}
        {--store : persist the scan to the Site Health Ledger}';

    protected $description = 'Report broken internal links across a site (from the projection inventory)';

    public function handle(ProjectionPublisher $publisher, BrokenLinkScanner $scanner): int
    {
        $site = $this->resolveSite((string) $this->argument('site'));
        if (! $site) {
            $this->error('Site not found: ' . $this->argument('site'));

            return self::FAILURE;
        }

        // Root is always a valid target (a site always has a homepage).
        $projectionsByUrl = ['/' => ['inventory' => ['outbound_links' => []]]];

        foreach ($site->pages()->where('status', 'published')->get() as $page) {
            $url = $publisher->urlForPath(LocalePaths::pagePath($site, $page));
            $projectionsByUrl[$url] = $publisher->build($site, $page, $url)->toArray();
        }
        foreach ($site->posts()->where('status', 'published')->get() as $post) {
            $url = $publisher->urlForPath(LocalePaths::postPath($site, $post));
            $projectionsByUrl[$url] = $publisher->build($site, $post, $url)->toArray();
        }

        $broken = $scanner->scan($projectionsByUrl);

        if ($this->option('store')) {
            SiteHealthReport::create([
                'site_id' => $site->id,
                'type' => 'broken_links',
                'data' => ['broken' => $broken],
                'summary' => [
                    'broken_count' => count($broken),
                    'pages_scanned' => count($projectionsByUrl) - 1, // minus the synthetic root entry
                ],
            ]);
            $this->line('Stored broken-link report to the Site Health Ledger.');
        }

        if ($broken === []) {
            $this->info("No broken internal links found across {$site->slug}.");

            return self::SUCCESS;
        }

        foreach ($broken as $b) {
            $this->line("BROKEN: {$b['source']} → {$b['target']}  [{$b['address']}]");
        }
        $this->warn(count($broken) . ' broken internal link(s).');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
