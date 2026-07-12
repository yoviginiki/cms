<?php

namespace App\Jobs\Library;

use App\Domain\Library\Services\LibraryThumbnailService;
use App\Models\BlockTemplate;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Renders + caches a preview thumbnail for a SITE-OWNED Library item after it is
 * saved (Builder P1 Slice E). Runs on the queue worker (which has Playwright);
 * re-establishes the tenant RLS context so the render's throwaway page inserts
 * pass WITH CHECK. No-ops safely when screenshotting is unavailable — the item
 * simply keeps its client-side wireframe fallback. System items are handled by
 * the `library:thumbnails` command, never here.
 */
class GenerateLibraryThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public string $templateId,
        public string $siteId,
        public string $tenantId,
    ) {}

    public function handle(LibraryThumbnailService $service): void
    {
        if (!$service->available()) {
            return;
        }

        DB::unprepared("SET app.current_tenant_id = '{$this->tenantId}'");

        $item = BlockTemplate::find($this->templateId);
        $site = Site::find($this->siteId);
        if (!$item || !$site || $item->is_system) {
            return; // site-owned items only
        }

        $url = $service->generateFor($item, $site);
        if ($url !== null) {
            DB::table('block_templates')->where('id', $item->id)
                ->update(['preview_image' => $url, 'updated_at' => now()]);
        }
    }
}
