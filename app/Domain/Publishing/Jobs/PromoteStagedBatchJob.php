<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\DeploymentGate;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\StalePathCleaner;
use App\Domain\References\Services\StalenessResolver;
use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Manual "Promote to live" of a staged stale batch.
 *
 * Runs on the builds worker, not in the HTTP request: the web PHP pool is
 * open_basedir-restricted to the admin's own docroots, so writing into a
 * custom-domain docroot (/home/cytechno/web/{domain}/public_html) failed with
 * "open_basedir restriction in effect" from the request. The CLI worker can
 * write every deploy target — same place full publishes and auto-promotes run.
 *
 * A failure leaves the batch staged with metadata.promote_error, which the
 * Stale pages screen shows.
 */
class PromoteStagedBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public string $deploymentId;
    public string $tenantId;

    public function __construct(Deployment $deployment)
    {
        // IDs, not models: RLS needs the tenant set before the model loads
        $this->deploymentId = $deployment->id;
        $this->tenantId = $deployment->site->tenant_id;
        $this->onConnection('builds');
    }

    public function handle(DeployService $deployService): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $deployment = Deployment::findOrFail($this->deploymentId);
        $site = $deployment->site;
        $stagingPath = $deployment->artifact_path;

        try {
            if ($deployment->status !== 'staged' || !$stagingPath || !is_dir($stagingPath)) {
                throw new \RuntimeException('The staged build is gone or no longer staged. Re-run the stale republish.');
            }
            app(DeploymentGate::class)->promote($site, $deployment, function (Deployment $d) use ($deployService, $stagingPath) {
                $deployService->deployPartial($d, $stagingPath);
            });
        } catch (\Throwable $e) {
            $deployment->refresh()->update([
                'status' => $deployment->status === 'deploying' ? 'staged' : $deployment->status,
                'metadata' => array_merge($deployment->metadata ?? [], ['current_step' => 'staged', 'promote_error' => $e->getMessage()]),
            ]);
            logger()->warning("Manual promote failed for site {$site->id}: {$e->getMessage()}");

            return;
        }

        $deployment->refresh();
        $built = collect($deployment->metadata['built'] ?? [])->all();

        // Clear flags ONLY for sources not re-flagged since the build (§7 D2).
        app(StalenessResolver::class)->clearBuiltIfUnchanged($built);

        try {
            app(StalePathCleaner::class)->removeFor($site, $built);
        } catch (\Throwable $e) {
            logger()->warning("Stale path cleanup failed for site {$site->id}: {$e->getMessage()}");
        }

        $metadata = $deployment->metadata ?? [];
        unset($metadata['promote_error']);
        $deployment->update([
            'status' => 'live',
            'completed_at' => now(),
            'metadata' => array_merge($metadata, ['current_step' => 'live']),
        ]);
    }
}
