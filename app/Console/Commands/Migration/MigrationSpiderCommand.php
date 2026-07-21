<?php

namespace App\Console\Commands\Migration;

use App\Domain\Migration\Services\SpiderRebuildService;
use Illuminate\Console\Command;

class MigrationSpiderCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'migration:spider
        {site : Site slug or id}
        {origin : Origin base URL (e.g. https://example.com)}
        {--only=* : Only these slugs}
        {--skip=* : Skip these slugs (hand-built pages)}
        {--dry : Extract but do not write}';

    protected $description = 'Rebuild imported pages/posts from the origin site\'s rendered HTML (native blocks, title hero, featured images, SEO meta, internal links)';

    public function handle(SpiderRebuildService $spider): int
    {
        $site = $this->resolveSite($this->argument('site'));
        if (!$site) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        $result = $spider->run($site, $this->argument('origin'), [
            'only' => array_filter((array) $this->option('only')),
            'skip' => array_filter((array) $this->option('skip')),
            'dry' => (bool) $this->option('dry'),
        ], fn (string $line) => $this->line($line));

        $this->info("done={$result['done']} skipped={$result['skipped']} missing=" . count($result['missing'])
            . ' empty=' . count($result['empty']) . ' failed=' . count($result['failed']));
        foreach ($result['missing'] as $m) {
            $this->warn("no imported match: {$m}");
        }
        foreach ($result['empty'] as $m) {
            $this->warn("nothing extracted: {$m}");
        }
        foreach ($result['failed'] as $slug => $err) {
            $this->error("failed {$slug}: {$err}");
        }
        $this->info("internal links rewritten in {$result['link_blocks_rewritten']} blocks");
        foreach ($result['unresolved_links'] as $l) {
            $this->warn("unresolved link: {$l}");
        }

        return self::SUCCESS;
    }
}
