<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Deployment;
use App\Models\Page;
use Tests\TestCase;

/**
 * FIX-B6b — rollback must actually restore a prior deployment's build, not
 * silently republish current content. FIX-B6a — a build a live symlink points
 * to must never be pruned.
 */
class RollbackTest extends TestCase
{
    public function test_rollback_repoints_live_site_to_target_build(): void
    {
        config(['queue.default' => 'sync']); // run publish jobs synchronously (config is cached)
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => array_merge($site->settings ?? [], ['homepage_id' => $page->id])]);

        $orchestrator = app(PublishOrchestrator::class);
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        // v1
        $page->update(['title' => 'VERSION ONE MARKER']);
        $v1 = $orchestrator->publish($site->fresh(), $this->owner, 'full');
        $this->assertSame('live', $v1->fresh()->status);
        $this->assertStringContainsString('VERSION ONE MARKER', file_get_contents("{$docroot}/index.html"));

        // v2
        $page->update(['title' => 'VERSION TWO MARKER']);
        $v2 = $orchestrator->publish($site->fresh(), $this->owner, 'full');
        $this->assertSame('live', $v2->fresh()->status);
        $this->assertStringContainsString('VERSION TWO MARKER', file_get_contents("{$docroot}/index.html"));

        // rollback to v1
        $rb = $orchestrator->rollback($site->fresh(), $v1, $this->owner);

        $this->assertSame('rolled_back', $rb->fresh()->status);
        // live site now serves v1 content again (NOT current DB, which is v2)
        $this->assertStringContainsString('VERSION ONE MARKER', file_get_contents("{$docroot}/index.html"));
        $this->assertStringNotContainsString('VERSION TWO MARKER', file_get_contents("{$docroot}/index.html"));
        // v1's build survived pruning
        $this->assertDirectoryExists(rtrim(config('publishing.staging_path'), '/') . "/{$v1->id}");
    }
}
