<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * F17 (audit 2026-09-22) — a change made while a deployment runs is never
 * silently dropped: it is recorded durably and republished in a coalesced
 * follow-up; a full build clears only the flags that existed when it
 * started; unpublish regenerates the indexes; SSH/zip sites fall back to a
 * full build instead of an unsupported delta.
 */
class AutoPublishFollowUpTest extends TestCase
{
    private Site $site;
    private Page $home;
    private string $docroot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $this->site = $this->createSiteWithPages(2);
        $this->home = Page::where('site_id', $this->site->id)->orderBy('sort_order')->firstOrFail();
        $this->site->update(['settings' => ['homepage_id' => $this->home->id, 'auto_publish' => true]]);
        $this->site = $this->site->fresh();
        $this->docroot = config('publishing.public_path') . '/' . $this->site->slug;
        app(PublishOrchestrator::class)->publish($this->site, $this->owner, 'full');
    }

    public function test_an_edit_during_a_build_is_recorded_and_published_by_the_follow_up(): void
    {
        $other = Page::where('site_id', $this->site->id)->where('id', '!=', $this->home->id)->firstOrFail();

        // A build is running (heartbeat fresh) …
        $running = Deployment::create([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => 'building',
            'triggered_by' => $this->owner->id, 'started_at' => now()->subMinute(),
            'metadata' => ['generation' => 5, 'heartbeat_at' => now()->toIso8601String()],
        ]);
        // … and the editor saves a page.
        $other->update(['title' => 'EDITED WHILE BUILDING']);
        app(AutoPublishService::class)->triggerIfEnabled($this->site, $this->owner, 'page_blocks', $other->id);

        $this->assertTrue($other->fresh()->needs_republish, 'the change must be recorded, not dropped');
        $this->assertSame(1, Deployment::where('site_id', $this->site->id)->whereIn('status', ['queued', 'building', 'deploying'])->count());

        // The running build finishes; its flag-clear must keep the newer flag …
        app(\App\Domain\References\Services\StalenessResolver::class)->clearForSite($this->site, $running->started_at);
        $this->assertTrue($other->fresh()->needs_republish);
        $running->update(['status' => 'live', 'completed_at' => now()]);

        // … and the follow-up republishes exactly that page.
        $follow = app(AutoPublishService::class)->followUp($this->site, $running->fresh());
        $this->assertNotNull($follow);
        $this->assertSame('stale_batch', $follow->type);
        $this->assertSame('live', $follow->fresh()->status);
        $this->assertFalse($other->fresh()->needs_republish);
        $this->assertStringContainsString('EDITED WHILE BUILDING', file_get_contents("{$this->docroot}/{$other->slug}/index.html"));

        // Nothing pending → no follow-up deployment.
        $this->assertNull(app(AutoPublishService::class)->followUp($this->site, $follow->fresh()));
    }

    public function test_ssh_sites_get_a_full_build_instead_of_an_unsupported_delta(): void
    {
        $this->site->update(['settings' => array_merge($this->site->settings, ['deploy_method' => 'ssh', 'deploy_ssh_host' => 'h', 'deploy_ssh_user' => 'u', 'deploy_ssh_path' => '/var/www'])]);
        \Illuminate\Support\Facades\Queue::fake();
        config(['queue.default' => 'redis']);

        app(AutoPublishService::class)->triggerIfEnabled($this->site->fresh(), $this->owner, 'page_blocks', $this->home->id);

        $latest = Deployment::where('site_id', $this->site->id)->latest('created_at')->first();
        $this->assertSame('full', $latest->type);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Domain\Publishing\Jobs\PublishSiteJob::class);
        \Illuminate\Support\Facades\Queue::assertNotPushed(RepublishStaleJob::class);
    }

    public function test_unpublishing_a_page_regenerates_sitemap_without_it(): void
    {
        $other = Page::where('site_id', $this->site->id)->where('id', '!=', $this->home->id)->firstOrFail();
        $this->assertStringContainsString($other->slug, file_get_contents("{$this->docroot}/sitemap.xml"));

        $other->update(['status' => 'draft']);
        // Delta batch for the now-unpublished page: nothing to build, but the
        // indexes must drop the URL.
        $dep = app(\App\Domain\Publishing\Services\DeploymentGate::class)->open($this->site, 'stale_batch', $this->owner, [
            'targets' => ['pages' => [$other->id], 'posts' => [], 'records' => []], 'auto_promote' => true,
        ], fn (Deployment $d) => RepublishStaleJob::dispatchSync($d));

        $this->assertSame('live', $dep->fresh()->status);
        $this->assertStringNotContainsString($other->slug, file_get_contents("{$this->docroot}/sitemap.xml"));
    }
}
