<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Page;
use Tests\TestCase;

/**
 * The static AssetPublisher deploy target must not outlive a build job: a
 * long-lived queue worker (or the next test) would otherwise write assets and
 * self-hosted fonts into an OLD build directory. Found while stabilizing the
 * suite (TokenProfileTest turned order-dependent).
 */
class DeployTargetLeakTest extends TestCase
{
    public function test_publish_job_clears_the_deploy_target_on_success_and_failure(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $site->update(['settings' => ['homepage_id' => Page::where('site_id', $site->id)->value('id')]]);

        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
        $this->assertNull(AssetPublisher::deployTarget(), 'deploy target leaked after a successful publish');

        // failing publish (rollback to a target that does not exist)
        $dep = \App\Models\Deployment::where('site_id', $site->id)->latest('created_at')->first();
        $dep->update(['artifact_path' => '/nonexistent/build']);
        try {
            app(PublishOrchestrator::class)->rollback($site->fresh(), $dep->fresh(), $this->owner);
        } catch (\Throwable) {
        }
        $this->assertNull(AssetPublisher::deployTarget(), 'deploy target leaked after a failed job');
    }
}
