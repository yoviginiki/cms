<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * FIX-B6c — deploy hardening: copyDeploy removes stale files (deleted pages),
 * and a stuck deployment is reaped (marked failed) instead of racing a live one.
 */
class DeployHardeningTest extends TestCase
{
    public function test_copy_deploy_removes_stale_files_but_keeps_dotfiles(): void
    {
        $root = storage_path('framework/testing/deploy-' . uniqid());
        $staging = "{$root}/staging";
        $target = "{$root}/target";
        File::ensureDirectoryExists("{$staging}/about");
        File::put("{$staging}/index.html", 'new home');
        File::put("{$staging}/about/index.html", 'new about');

        // Target has an old page the new build no longer contains, plus a dotdir.
        File::ensureDirectoryExists("{$target}/gone");
        File::ensureDirectoryExists("{$target}/.well-known");
        File::put("{$target}/index.html", 'old home');
        File::put("{$target}/gone/index.html", 'deleted page');
        File::put("{$target}/.well-known/acme", 'ssl');

        $deployment = new Deployment(['id' => \Illuminate\Support\Str::uuid()->toString()]);
        $ref = new \ReflectionMethod(DeployService::class, 'copyDeploy');
        $ref->setAccessible(true);
        $ref->invoke(app(DeployService::class), $staging, $target, $deployment);

        $this->assertSame('new home', file_get_contents("{$target}/index.html"));
        $this->assertFileExists("{$target}/about/index.html");
        $this->assertFileDoesNotExist("{$target}/gone/index.html"); // stale page removed
        $this->assertDirectoryDoesNotExist("{$target}/gone");
        $this->assertFileExists("{$target}/.well-known/acme"); // dotdir preserved

        File::deleteDirectory($root);
    }

    public function test_stuck_deployment_is_reaped_not_deleted_recent_one_blocks(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $orchestrator = app(PublishOrchestrator::class);
        config(['queue.default' => 'redis']); // don't actually run the job; keep it queued

        // A recent in-flight deployment blocks a new publish.
        $recent = Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'building',
            'triggered_by' => $this->owner->id,
        ]);
        try {
            $orchestrator->publish($site->fresh(), $this->owner, 'full');
            $this->fail('expected an in-progress error');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('in progress', $e->getMessage());
        }
        $this->assertSame('building', $recent->fresh()->status);

        // A stuck deployment (>30 min) is reaped -> marked failed, NOT deleted,
        // and a new publish proceeds.
        $recent->forceFill(['created_at' => now()->subMinutes(31)])->save();
        $new = $orchestrator->publish($site->fresh(), $this->owner, 'full');

        $this->assertSame('failed', $recent->fresh()->status);
        $this->assertNotNull($recent->fresh()); // record preserved, not deleted
        $this->assertNotSame($recent->id, $new->id);
    }
}
