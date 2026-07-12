<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Asset;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Track F3 — semantic HTML hardening of published output: lang source,
 * article landmark, skip link, single main, image dimensions/priority,
 * gallery asset enrichment, table scope/caption, cite, pagination ARIA.
 */
class SemanticHtmlTest extends TestCase
{
    private function makeSite(array $settings = []): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => $settings]);
    }

    private function build(Page|Post $content, Site $site): string
    {
        return app(BuildPageService::class)->build($content, $site->theme, $site);
    }

    private function addBlock(Page|Post $content, string $type, array $data, int $order = 0): Block
    {
        return Block::factory()->create([
            'blockable_id' => $content->id,
            'blockable_type' => $content instanceof Post ? 'post' : 'page',
            'type' => $type,
            'order' => $order,
            'data' => $data,
        ]);
    }

    public function test_html_lang_uses_site_default_language(): void
    {
        $site = $this->makeSite(['default_language' => 'de']);
        $page = Page::factory()->create(['site_id' => $site->id, 'title' => 'Hallo', 'status' => 'published']);

        $this->assertStringContainsString('<html lang="de">', $this->build($page, $site));
    }

    public function test_per_content_locale_overrides_site_default(): void
    {
        $site = $this->makeSite(['default_language' => 'de']);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'status' => 'published', 'seo_meta' => ['locale' => 'fr'],
        ]);

        $this->assertStringContainsString('<html lang="fr">', $this->build($page, $site));
    }

    public function test_posts_publish_inside_an_article_landmark(): void
    {
        $site = $this->makeSite();
        $post = Post::factory()->create(['site_id' => $site->id, 'category_id' => null, 'status' => 'published']);
        $this->addBlock($post, 'text', ['content' => 'Body text of the post']);

        $this->assertMatchesRegularExpression(
            '#<article>.*Body text of the post.*</article>#s',
            $this->build($post, $site)
        );
    }

    public function test_layout_has_skip_link_and_exactly_one_main(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertStringContainsString('id="main-content"', $html);
        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_first_image_gets_fetchpriority_and_dimensions(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $this->addBlock($page, 'image', [
            'url' => 'https://cdn.example.com/hero.jpg', 'alt' => 'Hero shot',
            'width' => 800, 'height' => 600,
        ]);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="600"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function test_gallery_images_are_enriched_from_the_asset_library(): void
    {
        $site = $this->makeSite();
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'alt_text' => 'A calm lake',
            'dimensions' => ['width' => 1024, 'height' => 768],
        ]);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $this->addBlock($page, 'gallery', [
            'images' => ["/api/v1/sites/{$site->id}/assets/{$asset->id}/serve"],
        ]);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('alt="A calm lake"', $html);
        $this->assertStringContainsString('width="1024"', $html);
        $this->assertStringContainsString('height="768"', $html);
    }

    public function test_table_emits_scope_and_caption(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $this->addBlock($page, 'table', [
            'headers' => ['Quarter', 'Revenue'],
            'rows' => [['Q1', '100'], ['Q2', '200']],
            'caption' => 'Quarterly revenue',
        ]);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('<th scope="col"', $html);
        $this->assertStringContainsString('Quarterly revenue</caption>', $html);
    }

    public function test_pullquote_attribution_uses_cite(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $this->addBlock($page, 'pullquote', ['text' => 'Simplicity is the soul.', 'attribution' => 'A. Author']);

        $this->assertMatchesRegularExpression('#<cite[^>]*>A\. Author</cite>#', $this->build($page, $site));
    }

    public function test_pagination_nav_has_aria_semantics(): void
    {
        $html = view('blocks.archive-pagination', [
            'data' => ['style' => 'numbered'],
            '__archiveCurrentPage' => 2,
            '__archiveTotalPages' => 3,
            '__archiveBaseUrl' => '/blog',
        ])->render();

        $this->assertStringContainsString('aria-label="Pagination"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }
}
