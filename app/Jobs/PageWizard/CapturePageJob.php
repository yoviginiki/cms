<?php

namespace App\Jobs\PageWizard;

use App\Models\PageWizard\PageWizardSession;
use App\Services\PageWizard\PageWizardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Layout-from-URL capture on the QUEUE WORKER: the web pool disables
 * proc_open, so Playwright/chromium can't be spawned from a request (same as
 * the theme wizard's CaptureReferenceJob). The controller creates the session
 * `capturing`; the UI polls until `drafting` or `capture_failed`.
 */
class CapturePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(
        public string $sessionId,
        public string $tenantId,
        public ?string $hint = null,
    ) {
    }

    public function handle(PageWizardService $wizard): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = PageWizardSession::find($this->sessionId);
        if (!$session) {
            return;
        }

        $wizard->completeUrlCapture($session, $this->hint);
    }

    public function failed(\Throwable $e): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = PageWizardSession::find($this->sessionId);
        if ($session && $session->status === 'capturing') {
            $session->update([
                'status' => 'capture_failed',
                'error' => 'Reading that page took too long — try uploading a screenshot instead.',
            ]);
        }
    }
}
