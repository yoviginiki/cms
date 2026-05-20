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
     * Render DTP magazine as HTML preview.
     */
    public function preview(Site $site, MagazineIssue $issue): Response
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $data = $this->renderService->render($issue);

        return response()->view('dtp-preview', [
            'issue' => $data['issue'],
            'spreads' => $data['spreads'],
            'pageCount' => $data['pageCount'],
            'frameCount' => $data['frameCount'],
        ]);
    }
}
