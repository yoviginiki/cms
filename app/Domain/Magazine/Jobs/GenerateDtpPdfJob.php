<?php

namespace App\Domain\Magazine\Jobs;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * PDF generation runs on the QUEUE WORKER (CLI PHP): the web pool disables
 * proc_open, so Chrome cannot be spawned from a request. The controller
 * dispatches this job and short-polls for the result file.
 */
class GenerateDtpPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(
        public string $issueId,
        public string $tenantId,
        public bool $withMarks = false,
    ) {
    }

    public static function resultPath(string $issueId, bool $withMarks = false): string
    {
        $safe = preg_replace('/[^a-f0-9\-]/', '', $issueId) . ($withMarks ? '-marks' : '');

        return storage_path("app/dtp-pdf/issue-{$safe}.pdf");
    }

    public static function errorPath(string $issueId, bool $withMarks = false): string
    {
        return self::resultPath($issueId, $withMarks) . '.error';
    }

    public function handle(DtpPdfService $pdfService): void
    {
        // RLS: set tenant context on the worker connection (same pattern as
        // PublishSiteJob)
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $result = self::resultPath($this->issueId, $this->withMarks);
        $error = self::errorPath($this->issueId, $this->withMarks);
        @unlink($error);

        try {
            $issue = MagazineIssue::findOrFail($this->issueId);
            $tmp = $pdfService->export($issue, $this->withMarks);
            @rename($tmp, $result) || copy($tmp, $result);
            @chmod($result, 0664);
        } catch (\Throwable $e) {
            @file_put_contents($error, mb_substr($e->getMessage(), 0, 500));
            throw $e;
        }
    }
}
