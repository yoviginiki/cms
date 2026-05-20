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
    public function status(Site $site, string $issueId): JsonResponse
    {
        $issue = MagazineIssue::where('site_id', $site->id)->findOrFail($issueId);

        return response()->json([
            'data' => $this->rolloutService->getReadinessReport($issue, $site->id),
        ]);
    }
}
