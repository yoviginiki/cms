<?php

namespace Tests\Feature\Publishing;

use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F01 (audit 2026-09-22) — POST sites/{site}/publish/clear must only touch the
 * live output OWNED by that site. The shared public root hosts every
 * slug-based site (as symlinks to builds) plus the ensodo.eu docroot itself;
 * a clear for site A must leave site B, unmanaged files and anything reached
 * through a symlink byte-for-byte untouched.
 */
class PublishClearScopeTest extends TestCase
{
    private string $root;
    private string $public;
    private string $builds;
    private string $tenantBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/clear-' . uniqid());
        $this->public = "{$this->root}/public";
        $this->builds = "{$this->root}/builds";
        $this->tenantBase = "{$this->root}/web";
        File::ensureDirectoryExists($this->public);
        File::ensureDirectoryExists($this->builds);
        File::ensureDirectoryExists($this->tenantBase);
        config([
            'publishing.public_path' => $this->public,
            'publishing.staging_path' => $this->builds,
            'publishing.tenant_base' => $this->tenantBase,
        ]);
        $this->setTenantScope($this->owner);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /** A live, symlink-deployed slug site: build dir + deployment row + public/{slug} -> build. */
    private function liveSlugSite(string $slug): array
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => $slug]);
        $dep = Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'live',
            'triggered_by' => $this->owner->id, 'metadata' => [],
        ]);
        $build = "{$this->builds}/{$dep->id}";
        File::ensureDirectoryExists("{$build}/about");
        File::put("{$build}/index.html", "home of {$slug}");
        File::put("{$build}/about/index.html", "about {$slug}");
        $dep->update(['artifact_path' => $build]);
        symlink($build, "{$this->public}/{$slug}");

        return [$site, $dep, $build];
    }

    private function treeHash(string $dir): string
    {
        $items = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $items[] = substr($f->getPathname(), strlen($dir)) . ':' . md5_file($f->getPathname());
        }
        sort($items);
        return md5(implode("\n", $items));
    }

    public function test_clear_for_one_slug_site_leaves_everything_else_untouched(): void
    {
        [$a] = $this->liveSlugSite('site-a');
        [, , $buildB] = $this->liveSlugSite('site-b');

        // Unmanaged content living in the shared root (the ensodo.eu docroot itself).
        File::put("{$this->public}/index.html", 'shared root home');
        File::put("{$this->public}/.htaccess", 'infra');
        File::ensureDirectoryExists("{$this->public}/aboutenso");
        File::put("{$this->public}/aboutenso/index.html", 'unmanaged dir');

        // A symlink pointing OUTSIDE the public root at a sentinel.
        File::ensureDirectoryExists("{$this->root}/sentinel");
        File::put("{$this->root}/sentinel/keep.txt", 'do not touch');
        symlink("{$this->root}/sentinel", "{$this->public}/escape");

        $hashB = $this->treeHash($buildB);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$a->id}/publish/clear", [], $this->apiHeaders())
            ->assertOk();

        // Site A's live folder is gone.
        $this->assertFalse(is_link("{$this->public}/site-a") || file_exists("{$this->public}/site-a"));

        // Site B: link and build byte-for-byte intact.
        $this->assertTrue(is_link("{$this->public}/site-b"));
        $this->assertSame($hashB, $this->treeHash($buildB));
        $this->assertStringEqualsFile("{$buildB}/index.html", 'home of site-b');

        // Unmanaged files, infra and the out-of-root sentinel untouched.
        $this->assertStringEqualsFile("{$this->public}/index.html", 'shared root home');
        $this->assertStringEqualsFile("{$this->public}/.htaccess", 'infra');
        $this->assertStringEqualsFile("{$this->public}/aboutenso/index.html", 'unmanaged dir');
        $this->assertTrue(is_link("{$this->public}/escape"));
        $this->assertStringEqualsFile("{$this->root}/sentinel/keep.txt", 'do not touch');
    }

    public function test_clear_is_a_separate_admin_permission_and_is_audited(): void
    {
        [$a] = $this->liveSlugSite('site-a');

        $editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($editor, 'sanctum')
            ->postJson("/api/v1/sites/{$a->id}/publish/clear", [], $this->apiHeaders())
            ->assertForbidden();
        $this->assertTrue(is_link("{$this->public}/site-a"));

        $this->actingAsAdmin()
            ->postJson("/api/v1/sites/{$a->id}/publish/clear", [], $this->apiHeaders())
            ->assertOk();
        $this->assertFalse(is_link("{$this->public}/site-a"));

        $this->assertDatabaseHas('activity_logs', ['site_id' => $a->id, 'action' => 'site.published_output_cleared']);
    }

    public function test_clear_for_a_custom_domain_site_removes_only_its_managed_files(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'clear-me.test']);
        $docroot = "{$this->tenantBase}/clear-me.test/public_html";
        File::ensureDirectoryExists("{$docroot}/about");
        File::ensureDirectoryExists("{$docroot}/.well-known");
        File::put("{$docroot}/index.html", 'managed home');
        File::put("{$docroot}/about/index.html", 'managed about');
        File::put("{$docroot}/.well-known/acme", 'ssl');
        File::put("{$docroot}/unmanaged.txt", 'operator file');

        // The live artifact defines exactly what the CMS manages there.
        $dep = Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'live',
            'triggered_by' => $this->owner->id, 'metadata' => [],
        ]);
        $artifact = "{$this->builds}/{$dep->id}";
        File::ensureDirectoryExists("{$artifact}/about");
        File::put("{$artifact}/index.html", 'managed home');
        File::put("{$artifact}/about/index.html", 'managed about');
        $dep->update(['artifact_path' => $artifact]);

        // Another site's build reachable via a symlink inside this docroot
        // (exactly the ensodo.eu topology) must not be entered.
        [, , $buildB] = $this->liveSlugSite('site-b');
        symlink($buildB, "{$docroot}/site-b");
        $hashB = $this->treeHash($buildB);

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$site->id}/publish/clear", [], $this->apiHeaders())
            ->assertOk();

        $this->assertFileDoesNotExist("{$docroot}/index.html");
        $this->assertFileDoesNotExist("{$docroot}/about/index.html");
        $this->assertDirectoryDoesNotExist("{$docroot}/about");
        $this->assertDirectoryExists($docroot); // the Hestia docroot itself stays
        $this->assertFileExists("{$docroot}/.well-known/acme");
        $this->assertStringEqualsFile("{$docroot}/unmanaged.txt", 'operator file');
        $this->assertTrue(is_link("{$docroot}/site-b"));
        $this->assertSame($hashB, $this->treeHash($buildB));
    }

    public function test_clear_with_nothing_live_is_a_noop(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'never-published']);
        File::put("{$this->public}/index.html", 'shared root home');

        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$site->id}/publish/clear", [], $this->apiHeaders())
            ->assertOk();

        $this->assertStringEqualsFile("{$this->public}/index.html", 'shared root home');
    }
}
