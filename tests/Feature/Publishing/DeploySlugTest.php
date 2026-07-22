<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Per-site deploy folder (settings.deploy_slug): the site publishes to
 * ensodo.eu/{deploy_slug} instead of its internal slug, and MOVING it (a new
 * deploy_slug) removes the old folder's link on the next publish so no stale
 * content keeps serving. The validation treats the shared docroot as the
 * collision authority.
 */
class DeploySlugTest extends TestCase
{
    private function publishableSite(): Site
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => array_merge($site->settings ?? [], ['homepage_id' => $page->id])]);

        return $site->fresh();
    }

    public function test_deploy_slug_defaults_and_overrides(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertSame($site->slug, $site->deploySlug());

        $site->update(['settings' => ['deploy_slug' => 'men-root-and-rise']]);
        $this->assertSame('men-root-and-rise', $site->fresh()->deploySlug());

        // Malformed overrides (separators/traversal) fall back to the slug.
        $site->update(['settings' => ['deploy_slug' => '../evil']]);
        $this->assertSame($site->slug, $site->fresh()->deploySlug());
    }

    public function test_publish_lands_in_the_custom_folder(): void
    {
        $site = $this->publishableSite();
        $site->update(['settings' => array_merge($site->settings, ['deploy_slug' => 'custom-place'])]);

        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');

        $public = config('publishing.public_path');
        $this->assertFileExists("{$public}/custom-place/index.html");
        $this->assertFileDoesNotExist("{$public}/{$site->slug}/index.html");
    }

    public function test_moving_the_folder_removes_the_old_one(): void
    {
        $site = $this->publishableSite();
        $public = config('publishing.public_path');

        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $this->assertTrue(file_exists("{$public}/{$site->slug}") || is_link("{$public}/{$site->slug}"));

        $site->update(['settings' => array_merge($site->fresh()->settings, ['deploy_slug' => 'moved-here'])]);
        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');

        $this->assertFileExists("{$public}/moved-here/index.html");
        // The old folder's link is gone — nothing stale keeps serving there.
        $this->assertFalse(is_link("{$public}/{$site->slug}"));
        $this->assertFileDoesNotExist("{$public}/{$site->slug}/index.html");
    }

    public function test_validation_rejects_a_taken_folder_and_bad_formats(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $public = config('publishing.public_path');
        File::ensureDirectoryExists("{$public}/already-there");

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => ['deploy_slug' => 'already-there'],
        ])->assertStatus(422);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => ['deploy_slug' => 'Bad/Path'],
        ])->assertStatus(422);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => ['deploy_slug' => 'free-folder'],
        ])->assertOk();
        $this->assertSame('free-folder', $site->fresh()->deploySlug());

        File::deleteDirectory("{$public}/already-there");
    }
}
