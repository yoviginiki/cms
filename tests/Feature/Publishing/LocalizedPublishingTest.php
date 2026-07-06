<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\LocalePaths;
use App\Models\Page;
use App\Models\Post;
use Tests\TestCase;

class LocalizedPublishingTest extends TestCase
{
    private function site(array $settings = [])
    {
        $site = $this->createSiteWithPages(1);
        $site->update(['settings' => array_merge($site->settings ?? [], $settings)]);

        return $site->fresh();
    }

    public function test_default_locale_paths_are_identical_to_legacy_publisher_logic(): void
    {
        $site = $this->site();
        $page = Page::where('site_id', $site->id)->firstOrFail();

        // legacy: ({slug}/)index.html with homepage collapsing to root
        $this->assertSame("{$page->slug}/index.html", LocalePaths::pagePath($site, $page));

        $site->update(['settings' => array_merge($site->settings ?? [], ['homepage_id' => $page->id])]);
        $this->assertSame('index.html', LocalePaths::pagePath($site->fresh(), $page));

        $post = Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => null]);
        $this->assertSame("{$post->slug}/index.html", LocalePaths::postPath($site, $post));
    }

    public function test_translated_content_publishes_under_locale_prefix_with_stripped_slug(): void
    {
        $site = $this->site(['default_language' => 'bg', 'languages' => ['en']]);
        $page = Page::where('site_id', $site->id)->firstOrFail();

        $en = $page->replicate(['id']);
        $en->slug = $page->slug . '-en';
        $en->seo_meta = array_merge($page->seo_meta ?? [], ['locale' => 'en']);
        $en->save();

        $this->assertSame("en/{$page->slug}/index.html", LocalePaths::pagePath($site, $en));
        $this->assertSame("/en/{$page->slug}/", LocalePaths::urlPath($site, $en));
    }

    public function test_localize_html_is_byte_identical_for_single_language_sites(): void
    {
        $site = $this->site();
        $page = Page::where('site_id', $site->id)->firstOrFail();
        $html = "<html><head><title>x</title></head><body><p>hi</p></body></html>";

        $this->assertSame($html, LocalePaths::localizeHtml($site, $page, $html));
    }

    public function test_localize_html_injects_hreflang_and_switcher_for_multilingual_sites(): void
    {
        $site = $this->site(['default_language' => 'bg', 'languages' => ['en']]);
        $page = Page::where('site_id', $site->id)->where('status', 'published')->firstOrFail();

        $en = $page->replicate(['id']);
        $en->slug = $page->slug . '-en';
        $en->seo_meta = array_merge($page->seo_meta ?? [], ['locale' => 'en']);
        $en->status = 'published';
        $en->save();

        $html = "<html><head><title>x</title></head><body><p>hi</p></body></html>";
        $out = LocalePaths::localizeHtml($site, $page, $html);

        $this->assertStringContainsString('hreflang="bg"', $out);
        $this->assertStringContainsString('hreflang="en"', $out);
        $this->assertStringContainsString('hreflang="x-default"', $out);
        $this->assertStringContainsString("/en/{$page->slug}/", $out);
        $this->assertStringContainsString('lang-switcher', $out);

        // a page that already renders a langswitcher block gets no fallback pill
        $withBlock = "<html><head></head><body><div class=\"lang-switcher-block\">x</div></body></html>";
        $out2 = LocalePaths::localizeHtml($site, $page, $withBlock);
        $this->assertSame(1, substr_count($out2, 'lang-switcher'));
    }

    public function test_static_cleaner_removes_file_and_prunes_empty_dirs(): void
    {
        $site = $this->site();
        $docroot = config('publishing.public_path') . '/' . $site->slug;
        @mkdir("{$docroot}/about", 0775, true);
        file_put_contents("{$docroot}/about/index.html", 'x');
        file_put_contents("{$docroot}/index.html", 'root');

        app(\App\Domain\Publishing\Services\StaticCleaner::class)->removePath($site, 'about/index.html');

        $this->assertFileDoesNotExist("{$docroot}/about/index.html");
        $this->assertDirectoryDoesNotExist("{$docroot}/about");
        $this->assertFileExists("{$docroot}/index.html"); // never touches siblings/docroot

        // cleanup
        @unlink("{$docroot}/index.html");
        @rmdir($docroot);
    }
}
