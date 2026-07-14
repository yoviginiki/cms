<?php

namespace App\Console\Commands;

use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ops backfill: rebuild search_text for collections (all sites, one site,
 * or one collection). Safe to run any time — read-modify of the tsvector
 * column only.
 */
class CollectionsReindexCommand extends Command
{
    protected $signature = 'collections:reindex
        {--site= : Limit to one site id or slug}
        {--collection= : Limit to one collection slug (requires --site)}';

    protected $description = 'Rebuild the search_text tsvector for collection records';

    public function handle(RecordService $records): int
    {
        foreach (Tenant::all() as $tenant) {
            $tid = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
            DB::unprepared("SET app.current_tenant_id = '{$tid}'");

            $sites = Site::where('tenant_id', $tenant->id)
                ->when($this->option('site'), fn ($q, $s) => $q->where('id', $s)->orWhere('slug', $s))
                ->get();

            foreach ($sites as $site) {
                $collections = ContentCollection::where('site_id', $site->id)
                    ->when($this->option('collection'), fn ($q, $c) => $q->where('slug', $c))
                    ->get();

                foreach ($collections as $collection) {
                    $n = $records->reindexSearchText($collection);
                    Cache::increment("colapi_ver:{$collection->id}");
                    $this->info("{$site->slug}/{$collection->slug}: {$n} records reindexed");
                }
            }
        }

        return self::SUCCESS;
    }
}
