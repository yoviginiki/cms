<?php

namespace App\Jobs\ThemeWizard;

use App\Models\ThemeWizard\WizardSession;
use App\Services\ThemeWizard\ThemeWizardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * The "from URL" reference capture runs on the QUEUE WORKER (CLI PHP): the web
 * pool disables proc_open, so Playwright/chromium cannot be spawned from a
 * request (same constraint as GenerateDtpPdfJob). The controller creates the
 * session in a `capturing` status, dispatches this, and the wizard UI polls the
 * session until it flips to `drafting` (ready) or `capture_failed`.
 */
class CaptureReferenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Screenshot (≤45s) + Opus vision + compile; generous but bounded.
    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(
        public string $sessionId,
        public string $tenantId,
        public ?string $hint = null,
    ) {
    }

    public function handle(ThemeWizardService $wizard): void
    {
        // RLS: set tenant context on the worker connection so the session is
        // visible and writable (same pattern as GenerateDtpPdfJob/PublishSiteJob).
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = WizardSession::find($this->sessionId);
        if (!$session) {
            return; // abandoned/deleted before the worker got to it
        }

        $wizard->completeUrlCapture($session, $this->hint);
    }

    /** A worker crash/timeout should still surface a clean failure to the UI. */
    public function failed(\Throwable $e): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $session = WizardSession::find($this->sessionId);
        if ($session && $session->status === 'capturing') {
            $session->update([
                'status' => 'capture_failed',
                'error' => 'Reading that site took too long — try uploading a screenshot instead.',
            ]);
        }
    }
}
