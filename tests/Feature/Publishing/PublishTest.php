<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Site;
use Tests\TestCase;

class PublishTest extends TestCase
{
    private function publishableSite(): Site
    {
        config(['queue.default' => 'sync']); // run the publish job synchronously
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => array_merge($site->settings ?? [], ['homepage_id' => $page->id])]);

        return $site->fresh();
    }

    public function test_can_trigger_publish(): void
    {
        $site = $this->publishableSite();
        $deployment = app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $this->assertNotNull($deployment->id);
    }

    public function test_publish_creates_deployment_record(): void
    {
        $site = $this->publishableSite();
        $deployment = app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $this->assertDatabaseHas('deployments', ['id' => $deployment->id, 'site_id' => $site->id]);
        $this->assertSame('live', $deployment->fresh()->status);
    }

    public function test_publish_generates_html_files(): void
    {
        $site = $this->publishableSite();
        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $docroot = config('publishing.public_path') . '/' . $site->slug;
        $this->assertFileExists("{$docroot}/index.html");
    }

    public function test_publish_generates_sitemap(): void
    {
        $site = $this->publishableSite();
        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $docroot = config('publishing.public_path') . '/' . $site->slug;
        $this->assertFileExists("{$docroot}/sitemap.xml");
    }

    public function test_publish_creates_page_versions(): void
    {
        $site = $this->publishableSite();
        $page = Page::where('site_id', $site->id)->firstOrFail();
        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $this->assertGreaterThan(0, PageVersion::where('page_id', $page->id)->count());
    }

    public function test_cannot_publish_while_another_in_progress(): void
    {
        config(['queue.default' => 'redis']); // keep the deployment queued, don't run it
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'building',
            'triggered_by' => $this->owner->id,
        ]);

        $this->expectException(\RuntimeException::class);
        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
    }
}
