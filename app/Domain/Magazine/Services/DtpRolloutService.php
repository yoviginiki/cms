<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;

/**
 * Controlled rollout for Magazine DTP editor.
 * Determines editor status, readiness, and provides entry point URLs.
 * No destructive migration — computed status only.
 */
class DtpRolloutService
{
    public function __construct(
        private DtpPreflightService $preflightService,
    ) {}

    /**
     * Get full readiness report for an issue.
     */
    public function getReadinessReport(MagazineIssue $issue, string $siteId): array
    {
        $featureEnabled = config('features.magazine_dtp_designer_enabled', false);
        $hasDtpData = $this->hasDtpData($issue);
        $preflightResult = $hasDtpData ? $this->preflightService->runForIssue($issue) : null;
        $hasBlockingErrors = $preflightResult && ($preflightResult['counts']['blocking'] ?? 0) > 0;
        $status = $this->computeStatus($featureEnabled, $hasDtpData, $hasBlockingErrors);

        $dtpStats = $hasDtpData ? [
            'spreads' => MagazineSpread::where('issue_id', $issue->id)->count(),
            'pages' => MagazineDtpPage::where('issue_id', $issue->id)->count(),
            'frames' => MagazineFrame::where('issue_id', $issue->id)->count(),
        ] : null;

        $blockingReasons = [];
        $warnings = [];

        if (!$featureEnabled) {
            $blockingReasons[] = 'DTP Designer feature flag is disabled.';
        }
        if (!$hasDtpData) {
            $warnings[] = 'No DTP data saved for this issue yet.';
        }
        if ($hasBlockingErrors) {
            $blockingReasons[] = 'Preflight has blocking errors (' . $preflightResult['counts']['blocking'] . ').';
        }

        return [
            'status' => $status,
            'canOpenDtp' => $featureEnabled,
            'canPromote' => $featureEnabled && $hasDtpData && !$hasBlockingErrors,
            'hasDtpData' => $hasDtpData,
            'dtpStats' => $dtpStats,
            'preflight' => $preflightResult ? [
                'status' => $preflightResult['status'],
                'score' => $preflightResult['score'],
                'counts' => $preflightResult['counts'],
            ] : null,
            'blockingReasons' => $blockingReasons,
            'warnings' => $warnings,
            'links' => [
                'legacyEditor' => "/admin/sites/{$siteId}/magazines/{$issue->id}/edit",
                'dtpEditor' => $featureEnabled ? "/admin/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-editor" : null,
                'dtpPreview' => $featureEnabled && $hasDtpData ? "/api/v1/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-preview" : null,
                'preflight' => $featureEnabled ? "/api/v1/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-preflight" : null,
            ],
        ];
    }

    /**
     * Compute editor status (no DB field — fully derived).
     */
    private function computeStatus(bool $featureEnabled, bool $hasDtpData, bool $hasBlockingErrors): string
    {
        if (!$featureEnabled || !$hasDtpData) return 'legacy';
        if ($hasBlockingErrors) return 'dtp_beta';
        return 'dtp_ready';
    }

    /**
     * Check if issue has any DTP data saved.
     */
    private function hasDtpData(MagazineIssue $issue): bool
    {
        return MagazineSpread::where('issue_id', $issue->id)->exists()
            || MagazineDtpPage::where('issue_id', $issue->id)->exists()
            || MagazineFrame::where('issue_id', $issue->id)->exists();
    }
}
