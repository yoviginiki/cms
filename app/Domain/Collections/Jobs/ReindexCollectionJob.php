<?php

namespace App\Domain\Collections\Jobs;

use App\Domain\Collections\Services\RecordService;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild search_text for every record of a collection. The tsvector is
 * normally maintained on record save — but editing the SCHEMA (searchable
 * flags, adding a searchable relation) changes what belongs in it without
 * touching any record, so CollectionService queues this after such edits.
 */
class ReindexCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $siteId,
        public string $collectionId,
        public string $tenantId,
    ) {
    }

    public function handle(RecordService $records): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $site = Site::find($this->siteId);
        $collection = ContentCollection::find($this->collectionId);
        if (!$site || !$collection) {
            return;
        }

        $records->reindexSearchText($collection);

        \Illuminate\Support\Facades\Cache::increment("colapi_ver:{$collection->id}");
    }
}
