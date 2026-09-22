<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\ProcessScheduledContentJob;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F20 (audit 2026-09-22) — scheduled publishing works with the real schema
 * (draft + due scheduled_at), across tenants under the restricted RLS role,
 * idempotently, and leaves a durable publish request when a deployment
 * cannot be created right away.
 */
class ScheduledPublishTest extends TestCase
{
    public function test_due_content_publishes_across_two_tenants_and_the_job_is_idempotent(): void
    {
        config(['queue.default' => 'sync']);

        // Tenant 1
        $this->setTenantScope($this->owner);
        $site1 = $this->createSiteWithPages(1);
        $home1 = Page::where('site_id', $site1->id)->firstOrFail();
        $site1->update(['settings' => ['homepage_id' => $home1->id]]);
        $due1 = Page::factory()->create(['site_id' => $site1->id, 'status' => 'draft', 'scheduled_at' => now()->subMinute()]);
        $future1 = Page::factory()->create(['site_id' => $site1->id, 'status' => 'draft', 'scheduled_at' => now()->addDay()]);
        $duePost1 = Post::factory()->create(['site_id' => $site1->id, 'status' => 'draft', 'scheduled_at' => now()->subMinutes(2)]);

        // Tenant 2 (its own owner, its own RLS context)
        $tenant2 = Tenant::factory()->create();
        $owner2 = User::factory()->owner()->create(['tenant_id' => $tenant2->id]);
        $this->setTenantScope($owner2);
        $site2 = Site::factory()->create(['tenant_id' => $tenant2->id]);
        $due2 = Page::factory()->create(['site_id' => $site2->id, 'status' => 'draft', 'scheduled_at' => now()->subMinute()]);

        (new ProcessScheduledContentJob())();

        $this->setTenantScope($this->owner);
        $this->assertSame('published', $due1->fresh()->status);
        $this->assertNull($due1->fresh()->scheduled_at);
        $this->assertNotNull($due1->fresh()->published_at);
        $this->assertSame('draft', $future1->fresh()->status);
        $this->assertSame('published', $duePost1->fresh()->status);
        $this->assertSame(1, Deployment::where('site_id', $site1->id)->count());
        $this->assertSame('live', Deployment::where('site_id', $site1->id)->first()->status);
        $this->assertFileExists(config('publishing.public_path') . "/{$site1->slug}/{$due1->slug}/index.html");

        $this->setTenantScope($owner2);
        $this->assertSame('published', $due2->fresh()->status, 'second tenant must be processed too');
        $this->assertSame(1, Deployment::where('site_id', $site2->id)->count());

        // Second run: nothing due → no duplicate deployments, nothing re-flipped.
        (new ProcessScheduledContentJob())();
        $this->setTenantScope($this->owner);
        $this->assertSame(1, Deployment::where('site_id', $site1->id)->count());
        $this->setTenantScope($owner2);
        $this->assertSame(1, Deployment::where('site_id', $site2->id)->count());
    }

    public function test_publish_request_survives_when_a_deployment_is_already_running(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $due = Page::factory()->create(['site_id' => $site->id, 'status' => 'draft', 'scheduled_at' => now()->subMinute()]);
        Deployment::create(['site_id' => $site->id, 'type' => 'full', 'status' => 'building', 'triggered_by' => $this->owner->id,
            'started_at' => now(), 'metadata' => ['heartbeat_at' => now()->toIso8601String()]]);

        (new ProcessScheduledContentJob())();

        $this->setTenantScope($this->owner);
        $this->assertSame('published', $due->fresh()->status);
        $this->assertTrue($due->fresh()->needs_republish, 'the publish request must stay durable');
        $this->assertSame(1, Deployment::where('site_id', $site->id)->count()); // no second active deployment
    }
}
