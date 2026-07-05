<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Jobs\GenerateDtpPdfJob;
use App\Domain\Magazine\Services\DtpZipService;
use App\Domain\Magazine\Services\DtpRenderService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Response;

class DtpPreviewController extends Controller
{
    public function __construct(
        private DtpRenderService $renderService,
    ) {}

    /**
     * Render DTP magazine preview using server-rendered HTML.
     * Uses same DtpRenderService as the public viewer for consistent sanitization.
     */
    public function preview(Site $site, MagazineIssue $issue): Response
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $data = $this->renderService->render($issue);

        if (empty($data['spreads'])) {
            return response()->view('dtp-preview', [
                'issue' => $data['issue'] ?? ['title' => $issue->title],
                'spreads' => [],
                'pageCount' => 0,
                'frameCount' => 0,
                'layoutMode' => 'single',
                'coverMode' => 'standalone',
            ]);
        }

        // Convert API asset URLs to public media URLs
        $spreads = json_decode(json_encode($data['spreads']), true);
        $adminUrl = rtrim(config('app.url', 'https://sys.ensodo.eu'), '/');
        array_walk_recursive($spreads, function (&$value) use ($adminUrl) {
            if (is_string($value) && preg_match('#/api/v1/sites/([^/]+)/assets/([^/]+)/serve#', $value, $m)) {
                $value = str_replace($m[0], "$adminUrl/media/{$m[1]}/{$m[2]}", $value);
            }
        });

        return response()->view('stillo-viewer', [
            'issue' => $data['issue'],
            'spreads' => $spreads,
            'pageCount' => $data['pageCount'],
            'viewerSettings' => $issue->layout_final['viewerSettings'] ?? [],
            'coverMode' => $data['coverMode'] ?? 'standalone',
            'fontsUrl' => $data['fontsUrl'] ?? null,
        ]);
    }

    /** standalone ZIP export — extract anywhere, reads without the CMS */
    public function zip(Site $site, MagazineIssue $issue, DtpZipService $zipService)
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }
        $path = $zipService->export($issue);
        $name = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $issue->title ?: 'magazine') . '-standalone.zip';

        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /**
     * Export the issue as PDF. Chrome runs on the queue worker (the web PHP
     * pool disables proc_open); we dispatch and short-poll for the result.
     */
    public function pdf(Site $site, MagazineIssue $issue)
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }
        $result = GenerateDtpPdfJob::resultPath($issue->id);
        $error = GenerateDtpPdfJob::errorPath($issue->id);
        @unlink($result);
        @unlink($error);
        GenerateDtpPdfJob::dispatch($issue->id, $issue->tenant_id);

        // short-poll: redis worker normally picks this up within a second
        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (is_file($result) && filesize($result) > 1000) {
                $name = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $issue->title ?: 'magazine') . '.pdf';

                return response()->download($result, $name, ['Content-Type' => 'application/pdf']);
            }
            if (is_file($error)) {
                return response()->json(['message' => 'PDF export failed: ' . file_get_contents($error)], 500);
            }
            usleep(500_000);
        }

        return response()->json(['message' => 'PDF export timed out — try again shortly.'], 504);
    }
}
