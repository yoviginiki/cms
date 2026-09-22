<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\DeploymentGate;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F15 (audit 2026-09-22) — one active deployment per site, enforced by the
 * database; heartbeat-based reaping; a fence before the live swap; staged
 * batches only promote onto the generation they were built on.
 */
class DeploymentGateTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function deployment(string $status, array $metadata = [], array $attrs = []): Deployment
    {
        return Deployment::create(array_merge([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => $status,
            'triggered_by' => $this->owner->id, 'metadata' => $metadata,
        ], $attrs));
    }

    public function test_database_refuses_a_second_active_deployment_for_a_site(): void
    {
        $this->deployment('building');
        try {
            DB::transaction(fn () => $this->deployment('queued'));
            $this->fail('expected the partial unique index to fire');
        } catch (UniqueConstraintViolationException) {
        }
        // a different site is unaffected
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        Deployment::create(['site_id' => $other->id, 'type' => 'full', 'status' => 'queued', 'triggered_by' => $this->owner->id, 'metadata' => []]);
        // terminal rows never collide
        $this->deployment('live');
        $this->deployment('failed');
        $this->assertSame(2, Deployment::where('site_id', $this->site->id)->whereIn('status', ['live', 'failed'])->count());
    }

    public function test_gate_reports_in_progress_and_assigns_monotonic_generations(): void
    {
        $gate = app(DeploymentGate::class);
        $a = $gate->open($this->site, 'full', $this->owner, [], fn () => null);
        $this->assertSame(1, $a->metadata['generation']);
        $this->assertSame(0, $a->metadata['base_generation']);

        try {
            $gate->open($this->site, 'stale_batch', $this->owner, [], fn () => null);
            $this->fail('expected in-progress refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('in progress', $e->getMessage());
        }

        $a->update(['status' => 'live', 'completed_at' => now()]);
        $b = $gate->open($this->site, 'rollback', $this->owner, [], fn () => null);
        $this->assertSame(2, $b->metadata['generation']);
        $this->assertSame(1, $b->metadata['base_generation']);
    }

    public function test_reaper_uses_the_heartbeat_not_the_row_age(): void
    {
        $gate = app(DeploymentGate::class);

        // Long, healthy build: created 2h ago, still reporting → not stuck.
        $healthy = $this->deployment('building', ['heartbeat_at' => now()->subMinutes(5)->toIso8601String()], ['started_at' => now()->subHours(2)]);
        Deployment::whereKey($healthy->id)->update(['created_at' => now()->subHours(2)]);
        $healthy = $healthy->fresh();
        $this->assertFalse($gate->isStuck($healthy));
        $this->assertSame(0, $gate->reapStuck($this->site));
        $this->assertSame('building', $healthy->fresh()->status);

        // Dead worker: heartbeat 40 min old → reaped.
        $healthy->update(['metadata' => ['heartbeat_at' => now()->subMinutes(40)->toIso8601String()]]);
        $this->assertSame(1, $gate->reapStuck($this->site));
        $this->assertSame('failed', $healthy->fresh()->status);
        $this->assertTrue($healthy->fresh()->metadata['reaped']);

        // Queued job that never started for over an hour → lost.
        $lost = $this->deployment('queued');
        Deployment::whereKey($lost->id)->update(['created_at' => now()->subMinutes(70)]);
        $this->assertTrue($gate->isStuck($lost->fresh()));
        $recent = $this->deployment('failed'); // free the active slot first
        $lost->update(['status' => 'failed']);
        $recent = $this->deployment('queued');
        $this->assertFalse($gate->isStuck($recent->fresh()));
    }

    public function test_fence_blocks_reaped_and_superseded_workers(): void
    {
        $gate = app(DeploymentGate::class);
        $old = $this->deployment('building', ['generation' => 3]);
        $this->assertTrue($gate->mayGoLive($old));

        // A newer generation went live meanwhile (the old one was reaped and a new publish ran).
        $old->update(['status' => 'failed']);
        $this->assertFalse($gate->mayGoLive($old));

        $old->update(['status' => 'building']);
        $this->deployment('live', ['generation' => 4]);
        $this->assertFalse($gate->mayGoLive($old));
    }

    public function test_a_reaped_full_publish_never_swaps_the_live_site(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => ['homepage_id' => $page->id]]);
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        $page->update(['title' => 'LIVE ONE']);
        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
        $this->assertStringContainsString('LIVE ONE', file_get_contents("{$docroot}/index.html"));

        // A deployment that the reaper already marked failed still gets its
        // job executed (late worker) — it must build nothing live.
        $page->update(['title' => 'ZOMBIE']);
        $zombie = Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'failed',
            'triggered_by' => $this->owner->id, 'metadata' => ['generation' => 99, 'reaped' => true],
        ]);
        (new \App\Domain\Publishing\Jobs\PublishSiteJob($zombie, 'full'))->handle(
            app(\App\Domain\Publishing\Services\BuildPageService::class), app(DeployService::class),
            app(\App\Domain\Publishing\Services\SitemapGenerator::class), app(\App\Domain\Publishing\Services\RobotsGenerator::class),
        );
        $this->assertStringContainsString('LIVE ONE', file_get_contents("{$docroot}/index.html"));
        $this->assertStringNotContainsString('ZOMBIE', file_get_contents("{$docroot}/index.html"));
    }

    public function test_promote_refuses_a_batch_built_on_an_older_generation(): void
    {
        $gate = app(DeploymentGate::class);
        $staged = $this->deployment('staged', ['base_generation' => 1]);
        $this->deployment('live', ['generation' => 2]); // site rebuilt after staging

        try {
            $gate->promote($this->site, $staged, fn () => $this->fail('must not deploy'));
            $this->fail('expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rebuilt', $e->getMessage());
        }
        $this->assertSame('staged', $staged->fresh()->status);

        // Same base generation → promoted; a failing deploy goes back to staged.
        $ok = $this->deployment('staged', ['base_generation' => 2]);
        $ran = false;
        $gate->promote($this->site, $ok, function (Deployment $d) use (&$ran) { $ran = true; $this->assertSame('deploying', $d->status); });
        $this->assertTrue($ran);

        $bad = $this->deployment('staged', ['base_generation' => 2]);
        $ok->update(['status' => 'live']);
        try {
            $gate->promote($this->site, $bad, fn () => throw new \RuntimeException('disk full'));
        } catch (\RuntimeException) {
        }
        $this->assertSame('staged', $bad->fresh()->status);
        $this->assertSame('disk full', $bad->fresh()->metadata['promote_error']);
    }
}
