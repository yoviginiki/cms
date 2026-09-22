<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutoPublishService
{
    /**
     * Change types that touch exactly ONE page/post and can be delta-published
     * (build just that entity + its shared archives) instead of rebuilding the
     * whole site. Site-wide changes (menu, template) still need a full rebuild
     * because they alter every rendered page.
     */
    private const ENTITY_SCOPED = ['post_updated', 'page_updated', 'page_blocks'];

    public function __construct(
        private PublishOrchestrator $orchestrator,
    ) {}

    /**
     * Trigger a publish via the queue worker (which has proper file permissions).
     * Always dispatches to queue — never writes files from the web process.
     *
     * When the change touches a single known page/post ($changeType is entity-
     * scoped and $changeId is set), delta-publish just that entity via a
     * stale_batch with auto_promote — avoiding a full rebuild of every post on
     * large sites (e.g. Art Day's ~7.7k posts). Site-wide changes still full-publish.
     */
    public function triggerIfEnabled(Site $site, ?User $user = null, string $changeType = 'full', ?string $changeId = null): void
    {
        if (!$this->isEnabled($site)) {
            return;
        }

        if (!$user) {
            $user = \Illuminate\Support\Facades\Auth::user();
        }
        if (!$user) {
            return;
        }

        try {
            if ($changeId !== null && in_array($changeType, self::ENTITY_SCOPED, true)) {
                $this->deltaPublish($site, $user, $changeType, $changeId);
            } else {
                $this->orchestrator->publish($site, $user, 'full');
                Log::info("Auto-publish queued for {$site->name} (trigger: {$changeType})");
            }
        } catch (\RuntimeException $e) {
            // A deployment is already in progress — skip silently
            Log::debug("Auto-publish skipped: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::warning("Auto-publish failed: {$e->getMessage()}");
        }
    }

    /**
     * Delta-publish a single changed page/post: stage only that entity (+ shared
     * archives, handled by RepublishStaleJob) and auto-promote it live. Mirrors
     * the manual entity-publish path (StaleContentController) — same guard, same
     * queued job, same auto_promote finish.
     */
    private function deltaPublish(Site $site, User $user, string $changeType, string $changeId): void
    {
        // Same guard as PublishOrchestrator / StaleContentController: never race
        // a build in progress. Skip silently — the entity keeps its edits and a
        // later publish (or the next edit) will carry them.
        $active = Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->exists();
        if ($active) {
            Log::debug("Auto-publish delta skipped for {$site->name}: a deployment is already in progress.");
            return;
        }

        $targets = ['pages' => [], 'posts' => [], 'records' => []];
        if ($changeType === 'post_updated') {
            $targets['posts'] = [$changeId];
        } else { // page_updated / page_blocks
            $targets['pages'] = [$changeId];
        }

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $user->id,
            'metadata' => [
                'current_step' => 'queued',
                'targets' => $targets,
                'auto_promote' => true,
                'source' => 'auto-publish-delta',
                'reason' => $changeType,
                'pages_total' => 1,
                'pages_built' => 0,
            ],
        ]);

        if (config('queue.default') !== 'sync') {
            RepublishStaleJob::dispatch($deployment);
        } else {
            RepublishStaleJob::dispatchSync($deployment);
        }

        Log::info("Auto-publish DELTA queued for {$site->name} ({$changeType} {$changeId})");
    }

    public function isEnabled(Site $site): bool
    {
        $settings = $site->settings ?? [];
        return ($settings['auto_publish'] ?? true) === true;
    }
}
