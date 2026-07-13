<?php

namespace Tests\Feature\Publishing;

use App\Domain\Pages\Services\PageService;
use App\Domain\Posts\Services\PostService;
use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\StalePathCleaner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * §7 hygiene batch: archive-zero-posts lint, slug-rename path recording,
 * client-input stripping, and hardened stale-path cleanup.
 */
class PublishHygieneTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_empty_archive_template_produces_lint_warning(): void
    {
        $cat = Category::factory()->create(['site_id' => $this->site->id, 'slug' => 'news']);
        Post::factory()->published()->create(['site_id' => $this->site->id, 'category_id' => $cat->id]);
        ThemeTemplate::create([
            'site_id' => $this->site->id, 'name' => 'Empty archive', 'slug' => 'empty-archive',
            'type' => 'archive', 'is_default' => true, 'created_by' => $this->owner->id,
        ]);

        $staging = storage_path('framework/testing/hyg-' . uniqid());
        $warnings = app(ArchiveBuildService::class)->buildAll($this->site, $staging);
        File::deleteDirectory($staging);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Empty archive', $warnings[0]);
        $this->assertStringContainsString('news', $warnings[0]);
    }

    public function test_archive_template_with_post_loop_passes_lint(): void
    {
        $cat = Category::factory()->create(['site_id' => $this->site->id, 'slug' => 'ok']);
        Post::factory()->published()->create(['site_id' => $this->site->id, 'category_id' => $cat->id]);
        $tpl = ThemeTemplate::create([
            'site_id' => $this->site->id, 'name' => 'Listing', 'slug' => 'listing',
            'type' => 'archive', 'is_default' => true, 'created_by' => $this->owner->id,
        ]);
        app(\App\Domain\Blocks\Services\BlockService::class)->syncBlocks($tpl, [[
            'type' => 'post-loop', 'level' => 'module', 'order' => 0, 'style' => [], 'children' => [],
            'data' => ['layout' => 'list', 'limit' => 10],
        ]]);

        $staging = storage_path('framework/testing/hyg-' . uniqid());
        $warnings = app(ArchiveBuildService::class)->buildAll($this->site, $staging);
        File::deleteDirectory($staging);

        $this->assertSame([], $warnings);
    }

    public function test_slug_rename_records_previous_path_for_published_content(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'old-name', 'status' => 'published']);
        app(PageService::class)->updatePage($page, ['slug' => 'new-name']);
        $this->assertContains('old-name/index.html', $page->fresh()->seo_meta['_previous_paths']);

        $cat = Category::factory()->create(['site_id' => $this->site->id, 'slug' => 'cat']);
        $post = Post::factory()->published()->create(['site_id' => $this->site->id, 'category_id' => $cat->id, 'slug' => 'old-post']);
        app(PostService::class)->updatePost($post, ['slug' => 'new-post']);
        $this->assertContains('cat/old-post/index.html', $post->fresh()->seo_meta['_previous_paths']);

        // drafts don't record (nothing published to clean up)
        $draft = Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'draft-a', 'status' => 'draft']);
        app(PageService::class)->updatePage($draft, ['slug' => 'draft-b']);
        $this->assertArrayNotHasKey('_previous_paths', $draft->fresh()->seo_meta ?? []);
    }

    public function test_clients_cannot_inject_previous_paths(): void
    {
        $page = Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'p1']);

        $this->actingAsOwner()->putJson("/api/v1/sites/{$this->site->id}/pages/{$page->id}", [
            'seo_meta' => ['_previous_paths' => ['../../etc/passwd.html'], 'title' => 'X'],
        ], $this->apiHeaders())->assertOk();

        $meta = $page->fresh()->seo_meta;
        $this->assertSame('X', $meta['title']);
        $this->assertArrayNotHasKey('_previous_paths', $meta);
    }

    public function test_stale_path_cleaner_removes_only_safe_paths(): void
    {
        $docroot = storage_path('framework/testing/docroot-' . uniqid());
        File::ensureDirectoryExists("{$docroot}/old-name");
        File::put("{$docroot}/old-name/index.html", 'stale');
        File::put("{$docroot}/keep.txt", 'not html');

        $page = Page::factory()->create([
            'site_id' => $this->site->id, 'slug' => 'new-name', 'status' => 'published',
            'seo_meta' => ['_previous_paths' => [
                'old-name/index.html',            // legit — removed
                '../outside/index.html',           // traversal — refused
                'keep.txt',                        // not .html — refused
                'new-name/index.html',             // current path — refused
            ]],
        ]);

        $removed = app(StalePathCleaner::class)->removeFor($this->site, [
            ['type' => 'page', 'id' => $page->id, 'path' => 'new-name/index.html'],
        ], $docroot);

        $this->assertSame(['old-name/index.html'], $removed);
        $this->assertFileDoesNotExist("{$docroot}/old-name/index.html");
        $this->assertDirectoryDoesNotExist("{$docroot}/old-name");
        $this->assertFileExists("{$docroot}/keep.txt");
        $this->assertArrayNotHasKey('_previous_paths', $page->fresh()->seo_meta ?? []);

        File::deleteDirectory($docroot);
    }
}
