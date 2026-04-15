<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishOrchestrator
{
    public function publish(Site $site, User $triggeredBy, string $type = 'partial'): Deployment
    {
        // Check no active deployment
        $active = Deployment::where('site_id', $site->id)
            ->whereIn('status', ['queued', 'building', 'deploying'])
            ->exists();

        if ($active) {
            throw new \RuntimeException('A deployment is already in progress for this site.');
        }

        // Advisory lock
        $lockKey = crc32($site->id);
        DB::statement("SELECT pg_advisory_lock({$lockKey})");

        try {
            $deployment = Deployment::create([
                'site_id' => $site->id,
                'type' => $type,
                'status' => 'queued',
                'triggered_by' => $triggeredBy->id,
                'metadata' => ['pages_total' => 0, 'pages_built' => 0, 'current_step' => 'queued'],
            ]);

            PublishSiteJob::dispatch($deployment, $type);

            return $deployment;
        } finally {
            DB::statement("SELECT pg_advisory_unlock({$lockKey})");
        }
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
