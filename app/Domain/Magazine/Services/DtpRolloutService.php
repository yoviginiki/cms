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
 *  - dtp_production:  Reserved — requires persisted editor_mode field (future)
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
        $previewRenderable = $this->canRenderPreview($featureEnabled, $hasDtpDocument);

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
                'productionStatePersisted' => false, // Future: editor_mode column
            ],
        ];
    }

    /**
     * Check if the preview render pipeline is available.
     *
     * Real render check — verifies:
     *  1. Feature flag is enabled
     *  2. DTP document exists (at least one spread or page)
     *  3. DtpRenderService is resolvable from the container
     *  4. Blade view 'dtp-preview' exists
     *
     * Fails closed: returns false if any component is missing.
     */
    private function canRenderPreview(bool $featureEnabled, bool $hasDtpDocument): bool
    {
        if (!$featureEnabled || !$hasDtpDocument) {
            return false;
        }

        // Verify the render service class exists and is resolvable
        try {
            app()->make(DtpRenderService::class);
        } catch (\Throwable) {
            return false;
        }

        // Verify the Blade view exists
        if (!view()->exists('dtp-preview')) {
            return false;
        }

        return true;
    }

    /**
     * Compute editor status (no DB field — fully derived).
     */
    private function computeStatus(bool $featureEnabled, bool $hasDtpDocument, bool $hasBlockingErrors): string
    {
        if (!$featureEnabled || !$hasDtpDocument) return 'legacy';
        if ($hasBlockingErrors) return 'dtp_beta';
        return 'dtp_ready';
        // dtp_production: not returned until editor_mode column exists
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
