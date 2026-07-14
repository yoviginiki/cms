<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\Deploy\SymlinkDeployStrategy;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A docroot left behind by the legacy copy/rename deploy is a REAL directory;
 * the symlink strategy must move it aside (preserved) instead of dying on
 * rename() EISDIR — the failure that blocked every re-publish of a
 * copy-deployed slug site.
 */
class SymlinkLegacyDocrootTest extends TestCase
{
    public function test_symlink_deploy_replaces_legacy_real_directory_and_preserves_it(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $deployment = Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'deploying',
            'triggered_by' => $this->owner->id,
        ]);

        $base = storage_path('app/test-deploy/' . uniqid());
        $staging = "{$base}/staging";
        $publicPath = "{$base}/docroot/site";
        File::ensureDirectoryExists($staging);
        File::put("{$staging}/index.html", 'new build');

        // Legacy real directory with content
        File::ensureDirectoryExists($publicPath);
        File::put("{$publicPath}/index.html", 'legacy copy-deployed build');

        config(['publishing.rollback_path' => "{$base}/rollback"]);

        (new SymlinkDeployStrategy())->deploy($staging, $publicPath, $deployment);

        $this->assertTrue(is_link($publicPath), 'docroot should now be a symlink');
        $this->assertSame('new build', File::get("{$publicPath}/index.html"));

        $moved = $deployment->fresh()->metadata['legacy_docroot_moved_to'] ?? null;
        $this->assertNotNull($moved);
        $this->assertSame('legacy copy-deployed build', File::get("{$moved}/index.html"));

        File::deleteDirectory($base);
    }
}
