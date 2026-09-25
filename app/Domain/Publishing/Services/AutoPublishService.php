<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutoPublishService
{
    /** Stale items a delta publish takes along; beyond this, the Stale pages screen handles them. */
    private const MAX_RIDE_ALONG = 100;

    /**
     * Change types that touch exactly ONE page/post and can be delta-published
     * (build just that entity + its shared archives) instead of rebuilding the
     * whole site. Site-wide changes (menu, template) still need a full rebuild
     * because they alter every rendered page.
     */
    private const ENTITY_SCOPED = ['post_updated', 'page_updated', 'page_blocks'];

    public function __construct(
        private PublishOrchestrator $orchestrator,
        private DeploymentGate $gate,
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

        $entityScoped = $changeId !== null && in_array($changeType, self::ENTITY_SCOPED, true);
        // Delta (per-file merge) is a LOCAL-docroot capability; SSH/zip sites
        // get the full build they can actually deploy (F17).
        $deltaCapable = (($site->settings ?? [])['deploy_method'] ?? 'local') === 'local';

        try {
            if ($entityScoped && $deltaCapable) {
                $this->deltaPublish($site, $user, $changeType, $changeId);
            } else {
                $this->orchestrator->publish($site, $user, 'full');
                Log::info("Auto-publish queued for {$site->name} (trigger: {$changeType})");
            }
        } catch (\RuntimeException $e) {
            // A deployment is in progress: the change must NOT be lost (F17).
            // Record it durably — the running deployment's finish hook picks
            // up everything flagged after it started (coalesced follow-up).
            $this->recordPending($site, $changeType, $changeId);
            Log::debug("Auto-publish deferred (deployment in progress): {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::warning("Auto-publish failed: {$e->getMessage()}");
        }
    }

    /** Durable "publish this again after the current build" marker. */
    private function recordPending(Site $site, string $changeType, ?string $changeId): void
    {
        try {
            $resolver = app(\App\Domain\References\Services\StalenessResolver::class);
            if ($changeId !== null && in_array($changeType, self::ENTITY_SCOPED, true)) {
                $model = $changeType === 'post_updated' ? \App\Models\Post::find($changeId) : \App\Models\Page::find($changeId);
                $model?->forceFill(['needs_republish' => true, 'needs_republish_reason' => 'Edited while a deployment was running'])->save();
            } else {
                $resolver->markSiteStale($site, "Changed ({$changeType}) while a deployment was running");
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-publish could not record the pending change: {$e->getMessage()}");
        }
    }

    /**
     * After a deployment finished: anything flagged since it started is
     * republished in ONE follow-up delta batch (F17). Called by the jobs.
     */
    public function followUp(Site $site, Deployment $finished): ?Deployment
    {
        if (!$this->isEnabled($site)) {
            return null;
        }
        $since = $finished->started_at ?? $finished->created_at;
        $pages = \App\Models\Page::where('site_id', $site->id)->where('needs_republish', true)
            ->where('status', 'published')->where('updated_at', '>', $since)->pluck('id')->all();
        $posts = \App\Models\Post::where('site_id', $site->id)->where('needs_republish', true)
            ->where('status', 'published')->where('updated_at', '>', $since)->pluck('id')->all();
        $siteStale = isset(($site->fresh()->settings ?? [])['stale']);
        if ($pages === [] && $posts === [] && !$siteStale) {
            return null;
        }
        $user = User::find($finished->triggered_by);
        if (!$user) {
            return null;
        }
        try {
            if ($siteStale || (($site->settings ?? [])['deploy_method'] ?? 'local') !== 'local') {
                return $this->orchestrator->publish($site, $user, 'full');
            }

            return $this->gate->open($site, 'stale_batch', $user, [
                'targets' => ['pages' => $pages, 'posts' => $posts, 'records' => []],
                'auto_promote' => true,
                'source' => 'auto-publish-followup',
                'reason' => 'Edited during deployment ' . $finished->id,
                'pages_total' => count($pages) + count($posts),
            ], function (Deployment $deployment) {
                if (config('queue.default') !== 'sync') {
                    RepublishStaleJob::dispatch($deployment);
                } else {
                    RepublishStaleJob::dispatchSync($deployment);
                }
            });
        } catch (\Throwable $e) {
            Log::warning("Auto-publish follow-up failed for {$site->name}: {$e->getMessage()}");

            return null;
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
        $targets = ['pages' => [], 'posts' => [], 'records' => []];
        if ($changeType === 'post_updated') {
            $targets['posts'] = [$changeId];
        } else { // page_updated / page_blocks
            $targets['pages'] = [$changeId];
        }

        // Ride along everything already flagged stale — above all the
        // dependents this very change just flagged (a page listing the edited
        // post). StaleAutoRepublisher stands down while auto-publish is on, so
        // without this those dependents stayed stale until a manual rebuild.
        $flagged = [
            'pages' => \App\Models\Page::where('site_id', $site->id)->where('needs_republish', true)->where('status', 'published')->limit(self::MAX_RIDE_ALONG + 1)->pluck('id')->all(),
            'posts' => \App\Models\Post::where('site_id', $site->id)->where('needs_republish', true)->where('status', 'published')->limit(self::MAX_RIDE_ALONG + 1)->pluck('id')->all(),
            'records' => \App\Models\Record::where('site_id', $site->id)->where('needs_republish', true)->limit(self::MAX_RIDE_ALONG + 1)->pluck('id')->all(),
        ];
        if (count($flagged['pages']) + count($flagged['posts']) + count($flagged['records']) <= self::MAX_RIDE_ALONG) {
            foreach ($flagged as $kind => $ids) {
                $targets[$kind] = array_values(array_unique([...$targets[$kind], ...$ids]));
            }
        } else {
            Log::info("Auto-publish delta for {$site->name}: more than " . self::MAX_RIDE_ALONG . ' stale items — not riding along; use Stale pages.');
        }
        $total = count($targets['pages']) + count($targets['posts']) + count($targets['records']);

        // Same gate as every other deployment kind (F15): throws when one is active.
        $this->gate->open($site, 'stale_batch', $user, [
            'targets' => $targets,
            'auto_promote' => true,
            'source' => 'auto-publish-delta',
            'reason' => $changeType,
            'pages_total' => $total,
        ], function (Deployment $deployment) {
            if (config('queue.default') !== 'sync') {
                RepublishStaleJob::dispatch($deployment);
            } else {
                RepublishStaleJob::dispatchSync($deployment);
            }
        });

        Log::info("Auto-publish DELTA queued for {$site->name} ({$changeType} {$changeId})");
    }

    public function isEnabled(Site $site): bool
    {
        $settings = $site->settings ?? [];
        return ($settings['auto_publish'] ?? true) === true;
    }
}
