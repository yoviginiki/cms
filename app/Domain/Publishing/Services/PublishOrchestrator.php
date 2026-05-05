<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Database\AdvisoryLock;
use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;

class PublishOrchestrator
{
    public function publish(Site $site, User $triggeredBy, string $type = 'partial'): Deployment
    {
        // Clear stale deployments stuck for more than 5 minutes
        Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->where('created_at', '<', now()->subMinutes(5))
            ->delete();

        // Check no active deployment
        $active = Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->exists();

        if ($active) {
            throw new \RuntimeException('A deployment is already in progress for this site.');
        }

        return AdvisoryLock::run("publish_site_{$site->id}", function () use ($site, $triggeredBy, $type) {
            $deployment = Deployment::create([
                'site_id' => $site->id,
                'type' => $type,
                'status' => 'queued',
                'triggered_by' => $triggeredBy->id,
                'metadata' => ['pages_total' => 0, 'pages_built' => 0, 'current_step' => 'queued'],
            ]);

            // Run synchronously for fast publish — no queue delay
            PublishSiteJob::dispatchSync($deployment, $type);

            return $deployment;
        });
    }

    public function rollback(Site $site, Deployment $targetDeployment, User $triggeredBy): Deployment
    {
        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'rollback',
            'status' => 'queued',
            'triggered_by' => $triggeredBy->id,
            'metadata' => ['rollback_to' => $targetDeployment->id],
        ]);

        PublishSiteJob::dispatch($deployment, 'rollback', $targetDeployment);

        return $deployment;
    }
}
