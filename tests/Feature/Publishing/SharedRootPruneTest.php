<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\DeployService;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Aug 2026 incident: ensodo.eu's own site deploys into the SHARED docroot
 * that also hosts every slug site (symlinks into storage/app/builds). Its
 * full publish pruned everything its build didn't contain — descending into
 * the symlinked sites and emptying vioiv, heikotera-com, men-root and docs
 * (only dotfiles survived). The shared root is now pruned against the site's
 * previous build manifest only, and a pruning deploy into a build dir is
 * refused outright.
 */
class SharedRootPruneTest extends TestCase
{
    private string $root;
    private string $public;
    private string $builds;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->root = storage_path('framework/testing/shared-root-' . uniqid());
        $this->public = "{$this->root}/public_html";
        $this->builds = "{$this->root}/builds";
        File::ensureDirectoryExists($this->public);
        File::ensureDirectoryExists($this->builds);
        config(['publishing.public_path' => $this->public, 'publishing.staging_path' => $this->builds]);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function deployment(string $status, ?string $artifact = null): Deployment
    {
        return Deployment::create([
            'site_id' => $this->site->id, 'type' => 'full', 'status' => $status,
            'triggered_by' => $this->owner->id, 'artifact_path' => $artifact,
            'completed_at' => $status === 'live' ? now()->subHour() : null, 'metadata' => [],
        ]);
    }

    private function copyDeploy(string $staging, string $target, Deployment $deployment): void
    {
        $ref = new \ReflectionMethod(DeployService::class, 'copyDeploy');
        $ref->setAccessible(true);
        $ref->invoke(app(DeployService::class), $staging, $target, $deployment);
    }

    public function test_shared_root_prune_removes_only_this_sites_old_pages(): void
    {
        // Previous build of the root site: home + a page that has since been deleted
        $previous = "{$this->builds}/previous";
        File::ensureDirectoryExists("{$previous}/gone");
        File::put("{$previous}/index.html", 'old home');
        File::put("{$previous}/gone/index.html", 'deleted page');
        $this->deployment('live', $previous);

        // Live shared root: our files + a slug site (symlink) + an operator folder
        File::ensureDirectoryExists("{$this->public}/gone");
        File::put("{$this->public}/index.html", 'old home');
        File::put("{$this->public}/gone/index.html", 'deleted page');
        $vioivBuild = "{$this->builds}/vioiv-build";
        File::ensureDirectoryExists("{$vioivBuild}/uslugi");
        File::put("{$vioivBuild}/index.html", 'VIOIV HOME');
        File::put("{$vioivBuild}/uslugi/index.html", 'VIOIV PAGE');
        File::put("{$vioivBuild}/.htaccess", 'x');
        symlink($vioivBuild, "{$this->public}/vioiv");
        File::ensureDirectoryExists("{$this->public}/operator-tool");
        File::put("{$this->public}/operator-tool/index.html", 'not ours');

        $staging = "{$this->builds}/next";
        File::ensureDirectoryExists($staging);
        File::put("{$staging}/index.html", 'new home');

        $this->copyDeploy($staging, $this->public, $this->deployment('deploying'));

        $this->assertSame('new home', file_get_contents("{$this->public}/index.html"));
        $this->assertDirectoryDoesNotExist("{$this->public}/gone");
        $this->assertSame('VIOIV HOME', file_get_contents("{$vioivBuild}/index.html"));
        $this->assertSame('VIOIV PAGE', file_get_contents("{$vioivBuild}/uslugi/index.html"));
        $this->assertFileExists("{$this->public}/operator-tool/index.html");
    }

    public function test_shared_root_without_a_previous_manifest_deletes_nothing(): void
    {
        File::put("{$this->public}/orphan.html", 'unknown owner');
        $staging = "{$this->builds}/next";
        File::ensureDirectoryExists($staging);
        File::put("{$staging}/index.html", 'new home');

        $this->copyDeploy($staging, $this->public, $this->deployment('deploying'));

        $this->assertFileExists("{$this->public}/orphan.html");
    }

    public function test_pruning_deploy_into_a_build_directory_is_refused(): void
    {
        $build = "{$this->builds}/some-site-build";
        File::ensureDirectoryExists($build);
        File::put("{$build}/index.html", 'live content');
        $staging = "{$this->root}/staging";
        File::ensureDirectoryExists($staging);
        File::put("{$staging}/other.html", 'x');

        try {
            $this->copyDeploy($staging, $build, $this->deployment('deploying'));
            $this->fail('expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Refusing to prune-deploy', $e->getMessage());
        }
        $this->assertFileExists("{$build}/index.html");
    }
}
