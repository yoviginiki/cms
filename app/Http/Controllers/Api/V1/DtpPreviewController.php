<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
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

        return response()->view('dtp-preview', [
            'issue' => $data['issue'],
            'spreads' => $spreads,
            'pageCount' => $data['pageCount'],
            'frameCount' => $data['frameCount'],
            'layoutMode' => $data['layoutMode'] ?? 'single',
            'coverMode' => $data['coverMode'] ?? 'standalone',
            'fontsUrl' => $data['fontsUrl'] ?? null,
        ]);
    }
}
