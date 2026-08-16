<?php

namespace App\Domain\Culture;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\DeployService;
use App\Models\ContentCollection;
use App\Models\Deployment;
use App\Models\Record;
use App\Models\Site;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the ArtDay events calendar page in sync with the culture-engine
 * collection. The /kalendar page renders a self-contained widget whose event
 * data is BAKED INTO THE PAGE (between /*ADC-DATA*​/ markers) so it works on the
 * published site AND the auth-gated editor preview alike (neither a client-side
 * fetch of the collection index nor the search API is available on the preview).
 *
 * On every event sync we re-bake the upcoming, dated events into that widget and
 * delta-publish just that one page. Dispatched from CultureEventSyncController
 * after the records upsert; runs on the CMS queue workers.
 *
 * No-op (safe) when the tenant has no page carrying the widget marker.
 */
class RefreshEventCalendarJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    /**
     * A sync sends events in chunks, so the controller dispatches one refresh per
     * chunk. ShouldBeUnique collapses them to a single job; dispatched with a
     * short delay, it runs once after the whole sync has landed.
     */
    public int $uniqueFor = 300;

    private const MARKER_OPEN = '/*ADC-DATA*/';
    private const MARKER_CLOSE = '/*/ADC-DATA*/';

    public function __construct(
        public string $siteId,
        public string $collectionId,
        public string $tenantId,
    ) {
    }

    public function uniqueId(): string
    {
        return $this->siteId . ':' . $this->collectionId;
    }

    public function handle(BuildPageService $build, DeployService $deploy): void
    {
        $tenant = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenant}'");

        $site = Site::find($this->siteId);
        $collection = ContentCollection::find($this->collectionId);
        if (! $site || ! $collection) {
            return;
        }

        // Find the page + block carrying the calendar widget marker.
        $target = null;
        foreach ($site->pages()->where('status', 'published')->get() as $page) {
            foreach ($page->blocks()->get() as $block) {
                $html = is_array($block->data) ? ($block->data['html'] ?? '') : '';
                if (str_contains($html, self::MARKER_OPEN)) {
                    $target = [$page, $block, $html];
                    break 2;
                }
            }
        }
        if ($target === null) {
            return; // this site has no calendar widget — nothing to do
        }
        [$page, $block, $html] = $target;

        // Re-bake upcoming, dated events (past + undated are excluded — a calendar
        // looks forward, and this keeps the inline payload well within limits).
        $today = now()->toDateString();
        $rows = [];
        $recordIds = [];
        foreach ($collection->records()->where('status', 'published')->get() as $r) {
            $d = is_array($r->data) ? $r->data : json_decode($r->data, true);
            $sd = $d['start_date'] ?? null;
            if (! $sd) {
                continue;
            }
            $day = substr((string) $sd, 0, 10);
            if ($day < $today) {
                continue;
            }
            // Minimal card data: [slug, title, date, city, venue, time, is_free, image].
            // The card opens the record's own page, which carries the full
            // description + official-source link — so nothing large is inlined here.
            $rows[] = [
                $r->slug, $d['title'] ?? '', $day, $d['city'] ?? '', $d['venue'] ?? '',
                $d['time'] ?? '', empty($d['is_free']) ? 0 : 1, (string) ($d['image_url'] ?? ''),
            ];
            $recordIds[] = $r->id;
        }
        usort($rows, fn ($a, $b) => strcmp($a[2], $b[2]));
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        $pattern = '/' . preg_quote(self::MARKER_OPEN, '/') . '.*?' . preg_quote(self::MARKER_CLOSE, '/') . '/s';
        $new = preg_replace($pattern, self::MARKER_OPEN . $json . self::MARKER_CLOSE, $html, 1);
        if ($new === null || $new === $html) {
            return; // regex failed or data unchanged — skip the publish
        }

        $block->update(['data' => array_merge($block->data, ['html' => $new])]);

        // Delta-publish just this page (skip if a deploy is already running).
        if (Deployment::where('site_id', $site->id)->whereIn('status', ['queued', 'building', 'deploying'])->exists()) {
            return;
        }
        $userId = User::where('tenant_id', $site->tenant_id)->value('id');
        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $userId,
            'metadata' => [
                'targets' => ['pages' => [$page->id], 'posts' => [], 'records' => $recordIds],
                'source' => 'culture-calendar-refresh',
                'events' => count($rows),
            ],
        ]);
        (new RepublishStaleJob($deployment))->handle($build);
        $deployment = $deployment->fresh();
        if ($deployment->status === 'staged' && $deployment->artifact_path) {
            $deploy->deployPartial($deployment, $deployment->artifact_path);
            $deployment->update(['status' => 'live', 'completed_at' => now()]);
        }
    }
}
