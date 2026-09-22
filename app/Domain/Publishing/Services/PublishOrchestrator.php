<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use App\Services\ActivityLogService;

class PublishOrchestrator
{
    public function __construct(
        private ActivityLogService $activityLog,
        private DeploymentGate $gate,
    ) {}

    public function publish(Site $site, User $triggeredBy, string $type = 'partial'): Deployment
    {
        // F15: creation, reap and the active check live in DeploymentGate —
        // one site lock + a DB-level "one active per site" index for every
        // deployment kind (full/partial/rollback/stale batch/promote).
        return $this->gate->open($site, $type, $triggeredBy, [], function (Deployment $deployment) use ($site, $type) {
            $this->activityLog->log('publish.started', $site->id, 'deployment', $deployment->id, ['type' => $type]);

            // Use async queue if configured, otherwise synchronous for instant feedback
            if (config('queue.default') !== 'sync') {
                PublishSiteJob::dispatch($deployment, $type);
            } else {
                PublishSiteJob::dispatchSync($deployment, $type);
            }
        });
    }

    public function rollback(Site $site, Deployment $targetDeployment, User $triggeredBy): Deployment
    {
        if ($targetDeployment->site_id !== $site->id) {
            throw new \RuntimeException('Rollback target belongs to another site.');
        }

        return $this->gate->open($site, 'rollback', $triggeredBy, ['rollback_to' => $targetDeployment->id], function (Deployment $deployment) use ($targetDeployment) {
            if (config('queue.default') !== 'sync') {
                PublishSiteJob::dispatch($deployment, 'rollback', $targetDeployment);
            } else {
                PublishSiteJob::dispatchSync($deployment, 'rollback', $targetDeployment);
            }
        });
    }
}
