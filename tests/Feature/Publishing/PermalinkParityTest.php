<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\LocalePaths;
use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Per-site permalink structure (settings.permalink):
 *  - default: posts at /{category}/{slug}/, category archives at /{slug}/
 *    (historical behaviour — must not change).
 *  - flat + category_base: posts at /{slug}/ and category archives at
 *    /category/{slug}/ — WordPress /%postname%/ parity for migrated sites.
 * Post/Category URL accessors, LocalePaths file paths and the archive builder
 * must all agree so links and published files never diverge.
 */
class PermalinkParityTest extends TestCase
{
    private function makeSite(array $permalink = []): Site
    {
        $this->setTenantScope($this->owner);
        $settings = $permalink ? ['permalink' => $permalink] : [];

        return Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => $settings]);
    }

    public function test_default_site_keeps_category_prefixed_post_urls(): void
    {
        $site = $this->makeSite();
        $cat = Category::factory()->create(['site_id' => $site->id, 'slug' => 'news']);
        $post = Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => $cat->id, 'slug' => 'hello']);
        $post->load('site', 'category');
        $cat->setRelation('site', $site);

        $this->assertSame('/news/hello', $post->url_path);
        $this->assertSame('news/hello/index.html', LocalePaths::postPath($site, $post));
        $this->assertFalse(LocalePaths::postIsFlat($site));
        $this->assertSame('', LocalePaths::categoryBase($site));
        $this->assertSame('/news', $cat->url_path);
    }

    public function test_flat_site_emits_root_post_and_category_based_archive_urls(): void
    {
        $site = $this->makeSite(['post_structure' => 'flat', 'category_base' => 'category']);
        $cat = Category::factory()->create(['site_id' => $site->id, 'slug' => 'news']);
        $post = Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => $cat->id, 'slug' => 'hello']);
        $post->load('site', 'category');
        $cat->setRelation('site', $site);

        // Post lives at the root even though it has a category (WP /%postname%/).
        $this->assertTrue(LocalePaths::postIsFlat($site));
        $this->assertSame('/hello', $post->url_path);
        $this->assertSame('hello/index.html', LocalePaths::postPath($site, $post));

        // Category archive gets the /category/ base.
        $this->assertSame('category/', LocalePaths::categoryBase($site));
        $this->assertSame('/category/news', $cat->url_path);
    }

    public function test_flat_site_writes_category_archive_under_category_base(): void
    {
        $site = $this->makeSite(['post_structure' => 'flat', 'category_base' => 'category']);
        $cat = Category::factory()->create(['site_id' => $site->id, 'slug' => 'news']);
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => $cat->id]);

        $staging = rtrim(config('publishing.staging_path'), '/') . '/test-permalink-flat';
        File::deleteDirectory($staging);
        File::makeDirectory($staging, 0775, true);

        app(ArchiveBuildService::class)->buildCategoryArchives($site, $staging);

        $this->assertFileExists("{$staging}/category/news/index.html");
        $this->assertFileDoesNotExist("{$staging}/news/index.html");

        File::deleteDirectory($staging);
    }
}
