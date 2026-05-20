<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpPreflightService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class DtpPreflightController extends Controller
{
    public function __construct(
        private DtpPreflightService $preflightService,
    ) {}

    /**
     * Run preflight checks on a DTP issue.
     */
    public function run(Site $site, MagazineIssue $issue): JsonResponse
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->preflightService->runForIssue($issue),
        ]);
    }
}
