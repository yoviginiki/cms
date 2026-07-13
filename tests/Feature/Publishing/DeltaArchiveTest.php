<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\AssetPublisher;
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

    public function test_archive_asset_urls_are_rewritten_to_static_paths(): void
    {
        \Illuminate\Support\Facades\Storage::fake('assets');
        $site = $this->makeSite();
        $img = \Illuminate\Http\UploadedFile::fake()->image('feat.jpg', 800, 600);
        $path = "sites/{$site->id}/assets/feat.jpg";
        \Illuminate\Support\Facades\Storage::disk('assets')->put($path, file_get_contents($img->getRealPath()));
        $asset = \App\Models\Asset::factory()->create([
            'site_id' => $site->id, 'storage_path' => $path,
            'mime_type' => 'image/jpeg', 'checksum' => str_repeat('b', 64), 'variants' => [],
        ]);
        $category = Category::factory()->create(['site_id' => $site->id, 'slug' => 'notes']);
        Post::factory()->published()->create([
            'site_id' => $site->id, 'category_id' => $category->id,
            'featured_image' => "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve",
        ]);
        // Mirror the live setup: archive template with a post-loop showing images
        $tpl = \App\Models\ThemeTemplate::create([
            'site_id' => $site->id, 'name' => 'Archive', 'slug' => 'archive-test',
            'type' => 'archive', 'is_default' => true, 'created_by' => $this->owner->id,
        ]);
        app(\App\Domain\Blocks\Services\BlockService::class)->syncBlocks($tpl, [[
            'type' => 'post-loop', 'level' => 'module', 'order' => 0, 'style' => [], 'children' => [],
            'data' => ['layout' => 'list', 'showImage' => true, 'limit' => 10],
        ]]);

        $target = storage_path('framework/testing/arch-target-' . uniqid());
        $staging = storage_path('framework/testing/arch-staging-' . uniqid());
        File::ensureDirectoryExists($target);
        AssetPublisher::reset();
        AssetPublisher::setDeployTarget($target);
        app(ArchiveBuildService::class)->buildAll($site, $staging);
        AssetPublisher::reset();

        $html = file_get_contents("{$staging}/notes/index.html");
        // archives must ship hashed static asset URLs, never API serve URLs
        // (the analytics beacon legitimately keeps an absolute /api/v1 URL)
        $this->assertStringNotContainsString("assets/{$asset->id}/serve", $html);
        $this->assertStringContainsString('/assets/files/' . str_repeat('b', 64) . '.jpg', $html);
        $this->assertFileExists("{$target}/assets/files/" . str_repeat('b', 64) . '.jpg');

        File::deleteDirectory($staging);
        File::deleteDirectory($target);
    }

    public function test_delta_ships_asset_files_inside_the_staged_build(): void
    {
        \Illuminate\Support\Facades\Storage::fake('assets');
        $site = $this->makeSite();
        $img = \Illuminate\Http\UploadedFile::fake()->image('feat2.jpg', 640, 480);
        $path = "sites/{$site->id}/assets/feat2.jpg";
        \Illuminate\Support\Facades\Storage::disk('assets')->put($path, file_get_contents($img->getRealPath()));
        $asset = \App\Models\Asset::factory()->create([
            'site_id' => $site->id, 'storage_path' => $path,
            'mime_type' => 'image/jpeg', 'checksum' => str_repeat('c', 64), 'variants' => [],
        ]);
        $post = Post::factory()->published()->create([
            'site_id' => $site->id, 'category_id' => null,
            'featured_image' => "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve",
        ]);
        \App\Models\Block::factory()->create([
            'blockable_id' => $post->id, 'blockable_type' => 'post', 'type' => 'image', 'order' => 0,
            'data' => ['asset_id' => $asset->id, 'alt' => 'x'],
        ]);

        $staging = $this->runDelta($site, ['pages' => [], 'posts' => [$post->id]]);

        // Assets must live INSIDE the staged build (they ship with the deploy);
        // writing them straight to the docroot loses them to prune/symlink swap.
        $this->assertFileExists("{$staging}/assets/files/" . str_repeat('c', 64) . '.jpg');
        $postHtml = file_get_contents("{$staging}/" . \App\Domain\Publishing\Services\LocalePaths::postPath($site, $post->fresh()));
        $this->assertStringContainsString('/assets/files/' . str_repeat('c', 64), $postHtml);

        File::deleteDirectory($staging);
    }
}
