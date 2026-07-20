<?php

namespace App\Domain\References\Services;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Record;
use App\Models\Site;
use App\Services\ActivityLogService;

/**
 * Phase 4.1b — AUTO-REPUBLISH toggle (per-site setting, DEFAULT OFF).
 *
 * When settings['auto_republish_stale'] is true, flagging pages stale queues a
 * stale-batch rebuild of the dependents through the existing atomic pipeline
 * WITH automatic promotion — the entity save/publish click IS the confirmation.
 * Queued (existing queue driver) so the triggering request returns fast.
 *
 * Skipped when full auto-publish is on (a full rebuild already covers every
 * dependent — no point double-building), and for site-wide staleness (theme/
 * located-menu changes need a full rebuild, not a page batch).
 */
class StaleAutoRepublisher
{
    public function __construct(
        private AutoPublishService $autoPublish,
        private ActivityLogService $activityLog,
    ) {
    }

    public function maybeQueue(Site $site, string $reason): ?Deployment
    {
        if ((($site->settings ?? [])['auto_republish_stale'] ?? false) !== true) {
            return null;
        }
        if ($this->autoPublish->isEnabled($site)) {
            return null; // the full-rebuild auto-publish supersedes the batch
        }
        if ((($site->settings ?? [])['deploy_method'] ?? 'local') !== 'local') {
            return null; // partial promote unsupported (rsync --delete / zip) — manual flow
        }

        $pageIds = Page::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
        $postIds = Post::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
        $recordIds = Record::where('site_id', $site->id)->where('needs_republish', true)->pluck('id')->all();
        if ($pageIds === [] && $postIds === [] && $recordIds === []) {
            return null;
        }

        // Dedupe: one batch at a time per site — a pending auto batch (or any
        // active deployment) already covers the current flags
        $pending = Deployment::where('site_id', $site->id)
            ->where(fn ($q) => $q
                ->whereIn('status', ['queued', 'building', 'deploying'])
                ->orWhere(fn ($q2) => $q2->where('type', 'stale_batch')->where('status', 'staged')
                    ->where('metadata->auto_promote', true)))
            ->exists();
        if ($pending) {
            return null;
        }

        // triggered_by is a non-nullable FK; outside HTTP context fall back to
        // the tenant's first user (same convention as scheduled publishing)
        $userId = auth()->id()
            ?? \App\Models\User::where('tenant_id', $site->tenant_id)->value('id');
        if (!$userId) {
            return null;
        }

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $userId,
            'metadata' => [
                'current_step' => 'queued',
                'targets' => ['pages' => $pageIds, 'posts' => $postIds, 'records' => $recordIds],
                'pages_total' => count($pageIds) + count($postIds) + count($recordIds),
                'pages_built' => 0,
                'auto_promote' => true,
                'reason' => $reason,
            ],
        ]);

        RepublishStaleJob::dispatch($deployment);

        $this->activityLog->log('stale.auto_republish_queued', $site->id, 'deployment', $deployment->id, [
            'reason' => $reason,
            'pages' => count($pageIds),
            'posts' => count($postIds),
            'records' => count($recordIds),
        ]);

        return $deployment;
    }
}
