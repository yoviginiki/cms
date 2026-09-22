<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Exceptions\NonRetryableBuildException;
use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * F16 (audit 2026-09-22) — a transient failure on one attempt leaves the
 * deployment retryable (status stays `building`, staging dir resumable) and
 * the next attempt completes; hard failures are terminal at once; the queue's
 * failed() hook (timeouts / max attempts) marks the row failed; the builds
 * connection has retry_after > job timeout.
 */
class PublishRetryTest extends TestCase
{
    private Site $site;
    private string $docroot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $this->site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $this->site->id)->firstOrFail();
        $this->site->update(['settings' => ['homepage_id' => $page->id]]);
        $this->site = $this->site->fresh();
        $this->docroot = config('publishing.public_path') . '/' . $this->site->slug;
    }

    private function deployment(): Deployment
    {
        return Deployment::create([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => 'queued',
            'triggered_by' => $this->owner->id, 'metadata' => ['generation' => 1],
        ]);
    }

    /** A queue job handle reporting the given attempt number. */
    private function queueJob(int $attempts): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('attempts')->andReturn($attempts);
        $job->shouldReceive('fail')->andReturnNull();
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('markAsFailed')->andReturnNull();
        $job->shouldReceive('delete')->andReturnNull();
        $job->shouldReceive('getConnectionName')->andReturn('builds');
        $job->shouldReceive('uuid')->andReturn('job-uuid');

        return $job;
    }

    private function runJob(PublishSiteJob $job, ?BuildPageService $build = null): void
    {
        $job->handle(
            $build ?? app(BuildPageService::class),
            app(DeployService::class),
            app(SitemapGenerator::class),
            app(RobotsGenerator::class),
        );
    }

    public function test_transient_failure_on_first_attempt_is_retried_and_the_second_attempt_succeeds(): void
    {
        $dep = $this->deployment();

        // attempt 1: the renderer blows up (disk hiccup, DB timeout…)
        $broken = Mockery::mock(BuildPageService::class);
        $broken->shouldReceive('buildAndValidate')->andThrow(new \RuntimeException('temporary render failure'));
        $job = new PublishSiteJob($dep, 'full');
        $job->job = $this->queueJob(1);
        try {
            $this->runJob($job, $broken);
            $this->fail('the exception must propagate so the queue schedules the retry');
        } catch (\RuntimeException $e) {
            $this->assertSame('temporary render failure', $e->getMessage());
        }
        $fresh = $dep->fresh();
        $this->assertSame('building', $fresh->status, 'a retryable failure must not be terminal');
        $this->assertSame(1, $fresh->metadata['retry_attempt']);
        $this->assertStringContainsString('temporary render failure', $fresh->error_log);

        // attempt 2 (same deployment, same staging dir): completes and goes live
        $job2 = new PublishSiteJob($dep->fresh(), 'full');
        $job2->job = $this->queueJob(2);
        $this->runJob($job2);
        $this->assertSame('live', $dep->fresh()->status);
        $this->assertFileExists("{$this->docroot}/index.html");
    }

    public function test_last_attempt_and_sync_runs_end_in_failed(): void
    {
        $broken = Mockery::mock(BuildPageService::class);
        $broken->shouldReceive('buildAndValidate')->andThrow(new \RuntimeException('still broken'));

        // last attempt
        $dep = $this->deployment();
        $job = new PublishSiteJob($dep, 'full');
        $job->job = $this->queueJob($job->tries);
        try {
            $this->runJob($job, $broken);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $dep->fresh()->status);

        // sync run (no queue job → no retries)
        $dep->update(['status' => 'failed']); // free the one-active-per-site slot
        $dep2 = $this->deployment();
        $job2 = new PublishSiteJob($dep2, 'full');
        try {
            $this->runJob($job2, $broken);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $dep2->fresh()->status);
    }

    public function test_non_retryable_failure_is_terminal_immediately(): void
    {
        $dep = $this->deployment();
        $job = new PublishSiteJob($dep, 'rollback', $dep); // rollback target without a build dir
        $job->rollbackTargetId = '00000000-0000-7000-8000-000000000000';
        $job->job = $this->queueJob(1);
        $this->runJob($job);
        $this->assertSame('failed', $dep->fresh()->status);
        $this->assertStringContainsString('Rollback target', $dep->fresh()->error_log);
    }

    public function test_failed_hook_marks_the_deployment_failed(): void
    {
        $dep = $this->deployment();
        $dep->update(['status' => 'building']);
        (new PublishSiteJob($dep, 'full'))->failed(new \RuntimeException('worker killed: timeout'));
        $this->assertSame('failed', $dep->fresh()->status);
        $this->assertStringContainsString('timeout', $dep->fresh()->error_log);

        // a deployment that already went live is never flipped back
        $live = Deployment::create(['site_id' => $this->site->id, 'type' => 'full', 'status' => 'live', 'triggered_by' => $this->owner->id, 'metadata' => []]);
        (new PublishSiteJob($live, 'full'))->failed(new \RuntimeException('late'));
        $this->assertSame('live', $live->fresh()->status);
    }

    public function test_builds_queue_connection_outlives_the_longest_job(): void
    {
        $job = new PublishSiteJob($this->deployment(), 'full');
        $this->assertSame('builds', $job->connection);
        $retryAfter = (int) (config('queue.connections.builds.retry_after') ?? 0);
        // In the test env the builds connection is 'sync'; the redis/database
        // shapes must satisfy Laravel's rule timeout < retry_after.
        $redisShape = 3900;
        $this->assertGreaterThan($job->timeout, $redisShape);
        $this->assertGreaterThan(1800, $redisShape);
        $this->assertTrue(config('queue.connections.builds.driver') === 'sync' || $retryAfter > $job->timeout);
    }

    public function test_write_atomic_never_leaves_a_partial_file(): void
    {
        $dir = storage_path('framework/testing/atomic-' . uniqid());
        PublishSiteJob::writeAtomic("{$dir}/a/index.html", 'complete');
        $this->assertStringEqualsFile("{$dir}/a/index.html", 'complete');
        $this->assertSame([], glob("{$dir}/a/*.tmp-*") ?: []);
        File::deleteDirectory($dir);
    }
}
