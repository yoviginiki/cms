<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\PromoteStagedBatchJob;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 2026-09-25: (1) "Promote to live" failed on custom-domain sites because it
 * wrote the docroot from the open_basedir-restricted web request — it now
 * runs on the builds worker. (2) With auto-publish on, dependents flagged by
 * an edit (a page listing the edited post) stayed stale forever — the delta
 * publish now takes every flagged item along.
 */
class StalePromoteAndRideAlongTest extends TestCase
{
    private Site $site;
    private Page $home;
    private Page $other;
    private string $docroot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $this->site = $this->createSiteWithPages(2);
        $this->home = Page::where('site_id', $this->site->id)->orderBy('sort_order')->firstOrFail();
        $this->other = Page::where('site_id', $this->site->id)->where('id', '!=', $this->home->id)->firstOrFail();
        $this->site->update(['settings' => ['homepage_id' => $this->home->id, 'auto_publish' => true]]);
        $this->site = $this->site->fresh();
        $this->docroot = config('publishing.public_path') . '/' . $this->site->slug;
        app(PublishOrchestrator::class)->publish($this->site, $this->owner, 'full');
    }

    public function test_delta_publish_takes_already_flagged_dependents_along(): void
    {
        // A dependent flagged stale by an earlier change (e.g. it lists an edited post)
        $this->other->update(['title' => 'DEPENDENT REBUILT']);
        $this->other->forceFill(['needs_republish' => true, 'needs_republish_reason' => "Post 'X' updated"])->saveQuietly();

        app(AutoPublishService::class)->triggerIfEnabled($this->site, $this->owner, 'page_blocks', $this->home->id);

        $delta = Deployment::where('site_id', $this->site->id)->where('type', 'stale_batch')->latest()->firstOrFail();
        $this->assertSame('live', $delta->status);
        $this->assertContains($this->other->id, $delta->metadata['targets']['pages']);
        $this->assertFalse($this->other->fresh()->needs_republish);
        $this->assertStringContainsString('DEPENDENT REBUILT', file_get_contents("{$this->docroot}/{$this->other->slug}/index.html"));
    }

    public function test_promote_is_queued_on_the_builds_worker_and_goes_live(): void
    {
        $this->other->update(['title' => 'PROMOTED BY WORKER']);
        $this->other->forceFill(['needs_republish' => true, 'needs_republish_reason' => 'manual'])->saveQuietly();

        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/stale/republish", ['page_ids' => [$this->other->id]], $this->apiHeaders())
            ->assertCreated();
        $staged = Deployment::where('site_id', $this->site->id)->where('status', 'staged')->latest()->firstOrFail();

        Queue::fake();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/stale/{$staged->id}/promote", [], $this->apiHeaders())
            ->assertStatus(202)->assertJsonPath('data.queued', true);
        Queue::assertPushed(PromoteStagedBatchJob::class, fn ($job) => $job->connection === 'builds' && $job->deploymentId === $staged->id);
        $this->assertSame('staged', $staged->fresh()->status, 'nothing is promoted inside the web request');

        (new PromoteStagedBatchJob($staged->fresh()))->handle(app(\App\Domain\Publishing\Services\DeployService::class));

        $this->assertSame('live', $staged->fresh()->status);
        $this->assertFalse($this->other->fresh()->needs_republish);
        $this->assertStringContainsString('PROMOTED BY WORKER', file_get_contents("{$this->docroot}/{$this->other->slug}/index.html"));
    }

    public function test_a_failed_promote_leaves_the_batch_staged_with_the_error(): void
    {
        $this->other->forceFill(['needs_republish' => true, 'needs_republish_reason' => 'manual'])->saveQuietly();
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/stale/republish", ['page_ids' => [$this->other->id]], $this->apiHeaders())
            ->assertCreated();
        $staged = Deployment::where('site_id', $this->site->id)->where('status', 'staged')->latest()->firstOrFail();
        \Illuminate\Support\Facades\File::deleteDirectory($staged->artifact_path);

        (new PromoteStagedBatchJob($staged))->handle(app(\App\Domain\Publishing\Services\DeployService::class));

        $staged->refresh();
        $this->assertSame('staged', $staged->status);
        $this->assertNotEmpty($staged->metadata['promote_error']);
        $this->assertTrue($this->other->fresh()->needs_republish);
    }
}
