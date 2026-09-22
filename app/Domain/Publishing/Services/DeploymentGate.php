<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Database\AdvisoryLock;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Site-scoped coordination for EVERY deployment kind (F15, audit 2026-09-22):
 * full, partial, rollback, stale batch (manual or auto-delta) and promotion.
 *
 *  - creation happens inside the site advisory lock, AFTER the active check
 *    and the reap, and is backed by a partial unique index (one active row
 *    per site) so a second request cannot slip in between check and insert;
 *  - every deployment carries a site-monotonic `generation`; a worker checks
 *    it before the live swap (fencing) and a staged batch remembers the
 *    live generation it was built on (`base_generation`);
 *  - stuck detection uses the worker heartbeat (`metadata.heartbeat_at`),
 *    not the row's age: a long healthy build that keeps reporting progress
 *    is never reaped, a dead one is.
 */
class DeploymentGate
{
    public const ACTIVE = ['queued', 'building', 'deploying'];

    /** A worker that has not reported for this long is considered dead. */
    public const HEARTBEAT_STALE_MINUTES = 30;

    /** A queued job that never started within this window is considered lost. */
    public const QUEUED_STALE_MINUTES = 60;

    public static function lockKey(Site $site): string
    {
        return "publish_site_{$site->id}";
    }

    /**
     * Create the next deployment for a site, or throw when one is active.
     * $dispatch receives the created row and MUST queue the work.
     *
     * @throws \RuntimeException when a deployment is already in progress
     */
    public function open(Site $site, string $type, User $by, array $metadata, callable $dispatch): Deployment
    {
        return AdvisoryLock::run(self::lockKey($site), function () use ($site, $type, $by, $metadata, $dispatch) {
            $this->reapStuck($site);

            if ($this->active($site)) {
                throw new \RuntimeException('A deployment is already in progress for this site.');
            }

            $generation = $this->nextGeneration($site);
            $metadata = array_merge([
                'current_step' => 'queued',
                'pages_total' => 0,
                'pages_built' => 0,
            ], $metadata, [
                'generation' => $generation,
                'base_generation' => $this->liveGeneration($site),
                'heartbeat_at' => now()->toIso8601String(),
            ]);

            try {
                $deployment = Deployment::create([
                    'site_id' => $site->id,
                    'type' => $type,
                    'status' => 'queued',
                    'triggered_by' => $by->id,
                    'metadata' => $metadata,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The DB-level guard fired (another connection won the race).
                throw new \RuntimeException('A deployment is already in progress for this site.');
            }

            $dispatch($deployment);

            return $deployment;
        });
    }

    /**
     * Run a live-touching step (promotion of a staged batch) under the same
     * site lock and active guard. The staged deployment becomes the active
     * one for the duration; $work performs the deploy.
     *
     * @throws \RuntimeException when a deployment is active or the batch is stale
     */
    public function promote(Site $site, Deployment $staged, callable $work): void
    {
        AdvisoryLock::run(self::lockKey($site), function () use ($site, $staged, $work) {
            $this->reapStuck($site);
            if ($this->active($site)) {
                throw new \RuntimeException('A deployment is already in progress for this site.');
            }
            $live = $this->liveGeneration($site);
            $base = (int) ($staged->metadata['base_generation'] ?? 0);
            if ($live > $base) {
                throw new \RuntimeException('The site was rebuilt after this batch was staged (base generation '
                    . $base . ', live ' . $live . '). Re-run the republish.');
            }

            $staged->update([
                'status' => 'deploying',
                'metadata' => array_merge($staged->metadata ?? [], ['current_step' => 'deploying', 'heartbeat_at' => now()->toIso8601String()]),
            ]);
            try {
                $work($staged);
            } catch (\Throwable $e) {
                // back to staged for the manual flow
                $staged->update([
                    'status' => 'staged',
                    'metadata' => array_merge($staged->metadata ?? [], ['current_step' => 'staged', 'promote_error' => $e->getMessage()]),
                ]);
                throw $e;
            }
        });
    }

    public function active(Site $site): bool
    {
        return Deployment::where('site_id', $site->id)->whereIn('status', self::ACTIVE)->exists();
    }

    /**
     * Mark dead deployments failed. Heartbeat-based: a worker updates
     * metadata.heartbeat_at on every status/progress write.
     */
    public function reapStuck(Site $site): int
    {
        $reaped = 0;
        foreach (Deployment::where('site_id', $site->id)->whereIn('status', self::ACTIVE)->get() as $dep) {
            if ($this->isStuck($dep)) {
                $dep->update([
                    'status' => 'failed',
                    'error_log' => 'Deployment reaped: the worker stopped reporting progress.',
                    'completed_at' => now(),
                    'metadata' => array_merge($dep->metadata ?? [], ['current_step' => 'failed', 'reaped' => true]),
                ]);
                $reaped++;
            }
        }

        return $reaped;
    }

    public function isStuck(Deployment $dep): bool
    {
        $hb = $dep->metadata['heartbeat_at'] ?? null;
        $last = $hb ? \Illuminate\Support\Carbon::parse($hb) : ($dep->started_at ?? $dep->created_at);
        if ($dep->status === 'queued' && !$dep->started_at) {
            return $dep->created_at->lt(now()->subMinutes(self::QUEUED_STALE_MINUTES));
        }

        return $last->lt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    /** Record that the worker is alive (cheap metadata write). */
    public static function heartbeat(Deployment $dep, array $extra = []): void
    {
        $dep->update(['metadata' => array_merge($dep->metadata ?? [], $extra, ['heartbeat_at' => now()->toIso8601String()])]);
    }

    /**
     * Fence: may THIS deployment still go live? False when it was reaped/
     * cancelled/superseded or a newer generation already went live.
     */
    public function mayGoLive(Deployment $dep): bool
    {
        $fresh = Deployment::find($dep->id);
        if (!$fresh || !in_array($fresh->status, ['building', 'deploying', 'staged'], true)) {
            return false;
        }
        $mine = (int) ($fresh->metadata['generation'] ?? 0);
        $newerLive = Deployment::where('site_id', $fresh->site_id)
            ->whereIn('status', ['live', 'rolled_back'])
            ->whereRaw("COALESCE((metadata->>'generation')::bigint, 0) > ?", [$mine])
            ->exists();

        return !$newerLive;
    }

    public function liveGeneration(Site $site): int
    {
        return (int) Deployment::where('site_id', $site->id)
            ->whereIn('status', ['live', 'rolled_back'])
            ->max(DB::raw("COALESCE((metadata->>'generation')::bigint, 0)"));
    }

    private function nextGeneration(Site $site): int
    {
        return 1 + (int) Deployment::where('site_id', $site->id)
            ->max(DB::raw("COALESCE((metadata->>'generation')::bigint, 0)"));
    }
}
