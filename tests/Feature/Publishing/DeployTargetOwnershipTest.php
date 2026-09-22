<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\DeployTargetResolver;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F03 (audit 2026-09-22) — a site's custom_domain must never be able to aim
 * the deploy at a web root the site was not provisioned for: reserved
 * (admin) domains are refused on create AND update, the deploy layer repeats
 * the check, a target claimed by another site is refused, and a directory
 * merely existing is not authorization.
 */
class DeployTargetOwnershipTest extends TestCase
{
    private string $root;
    private string $tenantBase;
    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/target-' . uniqid());
        $this->tenantBase = "{$this->root}/web";
        $this->staging = "{$this->root}/staging";
        File::ensureDirectoryExists($this->tenantBase);
        File::ensureDirectoryExists($this->staging);
        File::put("{$this->staging}/index.html", 'new build');
        config([
            'publishing.tenant_base' => $this->tenantBase,
            'publishing.public_path' => "{$this->root}/public",
            'publishing.staging_path' => "{$this->root}/builds",
            'publishing.reserved_domains' => ['sys.ensodo.eu', 'admin.example.test'],
        ]);
        $this->setTenantScope($this->owner);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function docroot(string $domain): string
    {
        $path = "{$this->tenantBase}/{$domain}/public_html";
        File::ensureDirectoryExists($path);
        return $path;
    }

    private function deployment(Site $site): Deployment
    {
        return Deployment::create([
            'site_id' => $site->id, 'type' => 'full', 'status' => 'deploying',
            'triggered_by' => $this->owner->id, 'metadata' => [],
        ]);
    }

    public function test_update_refuses_reserved_domains_like_create_does(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach (['sys.ensodo.eu', 'admin.example.test', 'SYS.ensodo.eu'] as $domain) {
            $this->actingAsOwner()
                ->putJson("/api/v1/sites/{$site->id}", ['custom_domain' => $domain], $this->apiHeaders())
                ->assertStatus(422)
                ->assertJsonValidationErrors('custom_domain');
        }
        $this->assertNull($site->fresh()->custom_domain);

        $this->actingAsOwner()
            ->postJson('/api/v1/sites', ['name' => 'X', 'custom_domain' => 'admin.example.test'], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_domain');
    }

    public function test_deploy_layer_refuses_a_reserved_domain_even_if_the_directory_exists(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        // Bypass the FormRequest (import/CLI/legacy rows can do that).
        $site->forceFill(['custom_domain' => 'admin.example.test'])->save();
        $docroot = $this->docroot('admin.example.test');
        File::put("{$docroot}/index.html", 'ADMIN PANEL');

        $dep = $this->deployment($site->fresh());
        try {
            app(DeployService::class)->deploy($dep, $this->staging);
            $this->fail('expected the deploy to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('reserved', strtolower($e->getMessage()));
        }
        $this->assertStringEqualsFile("{$docroot}/index.html", 'ADMIN PANEL');
    }

    public function test_deploy_refuses_a_target_provisioned_for_another_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'shop.test']);
        $docroot = $this->docroot('shop.test');
        File::put("{$docroot}/index.html", 'OTHER SITE LIVE');
        File::put("{$docroot}/" . DeployTargetResolver::MARKER, '00000000-0000-7000-8000-000000000bbb');

        $dep = $this->deployment($site);
        try {
            app(DeployService::class)->deploy($dep, $this->staging);
            $this->fail('expected the deploy to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('another site', strtolower($e->getMessage()));
        }
        $this->assertStringEqualsFile("{$docroot}/index.html", 'OTHER SITE LIVE');
    }

    public function test_first_deploy_claims_the_target_for_the_site(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'mine.test']);
        $docroot = $this->docroot('mine.test');

        app(DeployService::class)->deploy($this->deployment($site), $this->staging);

        $this->assertStringEqualsFile("{$docroot}/index.html", 'new build');
        $this->assertStringEqualsFile("{$docroot}/" . DeployTargetResolver::MARKER, $site->id);

        // Second deploy of the SAME site still works (marker is ours).
        File::put("{$this->staging}/index.html", 'second build');
        app(DeployService::class)->deploy($this->deployment($site), $this->staging);
        $this->assertStringEqualsFile("{$docroot}/index.html", 'second build');
        // A full deploy's prune never removes the ownership marker.
        $this->assertStringEqualsFile("{$docroot}/" . DeployTargetResolver::MARKER, $site->id);
    }

    public function test_missing_target_is_not_created_and_traversal_domains_are_refused(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'ghost.test']);
        try {
            app(DeployService::class)->deploy($this->deployment($site), $this->staging);
            $this->fail('expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist("{$this->tenantBase}/ghost.test");

        $resolver = app(DeployTargetResolver::class);
        foreach (['../etc', 'a/../b', 'evil\\x', '', '.', 'a b.test'] as $bad) {
            $this->assertNull($resolver->normalizeDomain($bad), "domain '{$bad}' must be rejected");
        }
        $this->assertSame('good.test', $resolver->normalizeDomain('Good.TEST.'));
    }

    public function test_domain_uniqueness_is_global_across_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherOwner = User::factory()->owner()->create(['tenant_id' => $otherTenant->id]);
        $this->setTenantScope($otherOwner);
        Site::factory()->create(['tenant_id' => $otherTenant->id, 'custom_domain' => 'taken.test']);

        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        // RLS hides the other tenant's row from the validator; the request
        // must still end in a clean 422, not a 500 from the unique index.
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$site->id}", ['custom_domain' => 'taken.test'], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_domain');

        $this->actingAsOwner()
            ->postJson('/api/v1/sites', ['name' => 'Dup', 'custom_domain' => 'taken.test'], $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_domain');
    }

    public function test_full_copy_deploy_never_enters_or_removes_symlinks_in_the_docroot(): void
    {
        // The ensodo.eu docroot IS the shared public root: other sites live
        // there as symlinks to their builds. A full publish of the custom
        // domain site prunes stale files — it must not descend into them.
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'shared.test']);
        $docroot = $this->docroot('shared.test');
        $foreignBuild = "{$this->root}/foreign-build";
        File::ensureDirectoryExists("{$foreignBuild}/about");
        File::put("{$foreignBuild}/index.html", 'foreign home');
        File::put("{$foreignBuild}/about/index.html", 'foreign about');
        symlink($foreignBuild, "{$docroot}/other-site");
        File::put("{$docroot}/stale.html", 'from a previous build');

        app(DeployService::class)->deploy($this->deployment($site), $this->staging);

        $this->assertStringEqualsFile("{$docroot}/index.html", 'new build');
        $this->assertFileDoesNotExist("{$docroot}/stale.html"); // real stale file pruned
        $this->assertTrue(is_link("{$docroot}/other-site"));
        $this->assertStringEqualsFile("{$foreignBuild}/index.html", 'foreign home');
        $this->assertStringEqualsFile("{$foreignBuild}/about/index.html", 'foreign about');
    }
}
