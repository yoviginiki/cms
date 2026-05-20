<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpRolloutService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class DtpRolloutController extends Controller
{
    public function __construct(
        private DtpRolloutService $rolloutService,
    ) {}

    /**
     * Get editor status and readiness report for an issue.
     */
    public function status(Site $site, MagazineIssue $issue): JsonResponse
    {
        // Enforce site ownership — 404 if issue doesn't belong to this site
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->rolloutService->getReadinessReport($issue, $site->id),
        ]);
    }
}
