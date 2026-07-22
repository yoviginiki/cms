<?php

namespace App\Jobs\SiteWizard;

use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\SiteWizardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * One pipeline step (or one page batch) per invocation, then re-dispatch —
 * every run stays far inside the queue timeout regardless of site size, and
 * the SPA's polling sees step-by-step progress. Runs on the worker because
 * the extraction shells out to Playwright (proc_open — same constraint as
 * CapturePageJob).
 */
class BuildSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public string $sessionId,
        public string $tenantId,
    ) {
    }

    public function handle(SiteWizardService $wizard): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = SiteWizardSession::find($this->sessionId);
        if (!$session || $session->status !== 'running') {
            return;
        }

        if ($wizard->runStep($session)) {
            self::dispatch($this->sessionId, $this->tenantId);
        }
    }

    public function failed(\Throwable $e): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = SiteWizardSession::find($this->sessionId);
        if ($session && $session->status === 'running') {
            $session->update([
                'status' => 'failed',
                'error' => 'The build took too long on one step — you can retry it.',
            ]);
        }
    }
}
