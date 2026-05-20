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
 *
 * Four rollout states:
 *  - legacy:          Feature flag off OR no usable DTP document (no spreads/pages)
 *  - dtp_beta:        DTP data exists but preflight has blocking errors
 *  - dtp_ready:       DTP data passes preflight (no blocking errors)
 *  - dtp_production:  Reserved — requires persisted editor_mode field (MAG-P8)
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
        $hasDtpDocument = $this->hasDtpDocument($issue);
        $preflightResult = $hasDtpDocument ? $this->preflightService->runForIssue($issue) : null;
        $hasBlockingErrors = $preflightResult && ($preflightResult['counts']['blocking'] ?? 0) > 0;
        $status = $this->computeStatus($featureEnabled, $hasDtpDocument, $hasBlockingErrors);

        $spreadCount = MagazineSpread::where('issue_id', $issue->id)->count();
        $pageCount = MagazineDtpPage::where('issue_id', $issue->id)->count();
        $frameCount = MagazineFrame::where('issue_id', $issue->id)->count();

        $dtpStats = $hasDtpDocument ? [
            'spreads' => $spreadCount,
            'pages' => $pageCount,
            'frames' => $frameCount,
        ] : null;

        $previewLinkAvailable = $featureEnabled && $hasDtpDocument;
        // Real render availability check deferred to MAG-P8.
        // Until then, previewRenderable is false — we only verify link presence.
        $previewRenderable = false;

        $blockingReasons = [];
        $warnings = [];

        if (!$featureEnabled) {
            $blockingReasons[] = 'DTP Designer feature flag is disabled.';
        }
        if ($featureEnabled && !$hasDtpDocument) {
            $warnings[] = 'No DTP document saved for this issue yet (requires at least one spread or page).';
        }
        if ($hasBlockingErrors) {
            $blockingReasons[] = 'Preflight has blocking errors (' . $preflightResult['counts']['blocking'] . ').';
        }

        return [
            'status' => $status,
            'canOpenDtp' => $featureEnabled,
            'canPromote' => $featureEnabled && $hasDtpDocument && !$hasBlockingErrors,
            'hasDtpData' => $hasDtpDocument,
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
                // React SPA route — not a Laravel route. Rendered by resources/admin/src/App.tsx.
                'dtpEditor' => $featureEnabled ? "/admin/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-editor" : null,
                'dtpPreview' => $previewLinkAvailable ? "/api/v1/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-preview" : null,
                'preflight' => $featureEnabled ? "/api/v1/sites/{$siteId}/magazine-issues/{$issue->id}/dtp-preflight" : null,
            ],
            'capabilities' => [
                'dtpFeatureEnabled' => $featureEnabled,
                'hasDtpDocument' => $hasDtpDocument,
                'hasSpreadOrPage' => $spreadCount > 0 || $pageCount > 0,
                'previewLinkAvailable' => $previewLinkAvailable,
                'previewRenderable' => $previewRenderable,
                'legacyFallbackAvailable' => true,
                'productionStatePersisted' => false, // Future MAG-P8: editor_mode column
            ],
        ];
    }

    /**
     * Compute editor status (no DB field — fully derived).
     *
     * States:
     *  - legacy:         flag off or no usable DTP document
     *  - dtp_beta:       has DTP doc but preflight has blocking errors
     *  - dtp_ready:      has DTP doc and preflight passes
     *  - dtp_production: reserved for persisted promotion (MAG-P8, requires editor_mode column)
     */
    private function computeStatus(bool $featureEnabled, bool $hasDtpDocument, bool $hasBlockingErrors): string
    {
        if (!$featureEnabled || !$hasDtpDocument) return 'legacy';
        if ($hasBlockingErrors) return 'dtp_beta';
        return 'dtp_ready';
        // dtp_production: not returned until editor_mode column exists (MAG-P8)
    }

    /**
     * Check if issue has a usable DTP document.
     * Requires at least one spread or page — frames alone are not enough.
     */
    private function hasDtpDocument(MagazineIssue $issue): bool
    {
        return MagazineSpread::where('issue_id', $issue->id)->exists()
            || MagazineDtpPage::where('issue_id', $issue->id)->exists();
    }
}
