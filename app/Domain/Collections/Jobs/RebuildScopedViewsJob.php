<?php

namespace App\Domain\Collections\Jobs;

use App\Domain\Collections\Queries\ScopedViewManager;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild a site's SQL-mode scoped views after a collection schema change
 * (the views' typed columns are derived from the schema). Queued — a full
 * schema drop+recreate shouldn't block the request.
 */
class RebuildScopedViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $siteId, public string $tenantId)
    {
    }

    public function handle(ScopedViewManager $views): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $site = Site::find($this->siteId);
        if ($site) {
            $views->rebuildSite($site);
        }
    }
}
