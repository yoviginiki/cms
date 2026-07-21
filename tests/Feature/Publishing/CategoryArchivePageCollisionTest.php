<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Category archives write to /{slug}/index.html AFTER pages build, so a
 * category sharing a slug with a published page (typical after a WordPress
 * import) used to silently overwrite the page. The page must win.
 */
class CategoryArchivePageCollisionTest extends TestCase
{
    public function test_category_archive_skips_slug_owned_by_published_page(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $category = Category::factory()->create(['site_id' => $site->id, 'name' => 'Статии', 'slug' => 'stories']);
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => $category->id]);
        Page::factory()->published()->create(['site_id' => $site->id, 'slug' => 'stories', 'title' => 'Статии']);

        $staging = storage_path('app/builds/test-archive-collision');
        File::deleteDirectory($staging);
        File::makeDirectory($staging, 0775, true);

        $pageMarker = '<html><body>REAL PAGE</body></html>';
        File::makeDirectory("{$staging}/stories", 0775, true);
        File::put("{$staging}/stories/index.html", $pageMarker);

        $warnings = app(ArchiveBuildService::class)->buildCategoryArchives($site, $staging);

        $this->assertSame($pageMarker, File::get("{$staging}/stories/index.html"), 'archive overwrote the page');
        $this->assertNotEmpty(array_filter($warnings, fn ($w) => str_contains($w, "'stories'")));

        File::deleteDirectory($staging);
    }

    public function test_category_archive_still_builds_without_collision(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $category = Category::factory()->create(['site_id' => $site->id, 'name' => 'Guides', 'slug' => 'guides']);
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => $category->id]);

        $staging = storage_path('app/builds/test-archive-no-collision');
        File::deleteDirectory($staging);
        File::makeDirectory($staging, 0775, true);

        app(ArchiveBuildService::class)->buildCategoryArchives($site, $staging);

        $this->assertFileExists("{$staging}/guides/index.html");

        File::deleteDirectory($staging);
    }
}
