<?php

namespace Tests\Feature\Sites;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Deleting a site must take its live static output down with it — a
 * soft-deleted site's folder must not keep serving from the shared docroot.
 */
class SiteDeleteCleanupTest extends TestCase
{
    public function test_deleting_a_published_site_removes_its_live_folder(): void
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $site->update(['settings' => array_merge($site->settings ?? [], ['homepage_id' => $page->id])]);

        app(PublishOrchestrator::class)->publish($site->fresh(), $this->owner, 'full');
        $live = config('publishing.public_path') . '/' . $site->deploySlug();
        $this->assertTrue(is_link($live) || is_dir($live));

        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$site->id}")->assertStatus(204);

        $this->assertFalse(is_link($live));
        $this->assertDirectoryDoesNotExist($live);
        $this->assertNull(Site::find($site->id)); // soft-deleted
    }

    public function test_deleting_an_unpublished_site_just_soft_deletes(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->deleteJson("/api/v1/sites/{$site->id}")->assertStatus(204);
        $this->assertNull(Site::find($site->id));
    }
}
