<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Category;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * §7 D1 — delta publishes must rebuild the blog index and archives when the
 * batch contains posts: a changed post alters every archive that lists it.
 */
class DeltaArchiveTest extends TestCase
{
    private function makeSite(array $settings = []): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => $settings]);
    }

    private function runDelta(Site $site, array $targets): string
    {
        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $this->owner->id,
            'metadata' => ['targets' => $targets],
        ]);

        (new RepublishStaleJob($deployment))->handle(app(BuildPageService::class));

        return storage_path("app/builds/{$deployment->id}");
    }

    public function test_post_delta_rebuilds_blog_index_archives_and_category_feed(): void
    {
        $site = $this->makeSite();
        $category = Category::factory()->create(['site_id' => $site->id, 'name' => 'Guides', 'slug' => 'guides']);
        $post = Post::factory()->published()->create([
            'site_id' => $site->id, 'category_id' => $category->id, 'title' => 'Fresh Delta Post',
        ]);

        $staging = $this->runDelta($site, ['pages' => [], 'posts' => [$post->id]]);

        $this->assertFileExists("{$staging}/blog/index.html");
        $this->assertStringContainsString('Fresh Delta Post', file_get_contents("{$staging}/blog/index.html"));
        $this->assertFileExists("{$staging}/guides/index.html");
        $this->assertStringContainsString('Fresh Delta Post', file_get_contents("{$staging}/guides/index.html"));
        $this->assertFileExists("{$staging}/guides/feed.xml");
        $this->assertStringContainsString('Fresh Delta Post', file_get_contents("{$staging}/guides/feed.xml"));

        File::deleteDirectory($staging);
    }

    public function test_page_only_delta_skips_archive_rebuild(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->published()->create(['site_id' => $site->id]);
        // a published post exists, but is NOT in the batch
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => null]);

        $staging = $this->runDelta($site, ['pages' => [$page->id], 'posts' => []]);

        $this->assertFileDoesNotExist("{$staging}/blog/index.html");

        File::deleteDirectory($staging);
    }

    public function test_archive_lang_uses_site_default_language(): void
    {
        $site = $this->makeSite(['default_language' => 'de']);
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => null]);

        $staging = storage_path('framework/testing/archives-' . uniqid());
        app(ArchiveBuildService::class)->buildAll($site, $staging);

        $this->assertStringContainsString('<html lang="de">', file_get_contents("{$staging}/blog/index.html"));

        File::deleteDirectory($staging);
    }
}
