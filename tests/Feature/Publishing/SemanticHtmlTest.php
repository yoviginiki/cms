<?php

namespace Tests\Feature\Publishing;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Asset;
use App\Models\Block;
use App\Models\Grid;
use App\Models\GridPosition;
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

    public function test_grid_layout_emits_landmarks_and_article_for_posts(): void
    {
        $site = $this->makeSite();
        $post = Post::factory()->create(['site_id' => $site->id, 'category_id' => null, 'status' => 'published']);
        $this->addBlock($post, 'text', ['content' => 'Grid post body']);

        $grid = Grid::create([
            'site_id' => $site->id, 'name' => 'G', 'slug' => 'g-landmarks',
            'col_tracks' => '1fr', 'row_tracks' => 'auto auto auto auto',
            'areas' => '"header" "content" "main" "footer"', 'is_preset' => false,
        ]);
        foreach ([['header', 'static'], ['content', 'canvas'], ['main', 'canvas'], ['footer', 'static']] as [$area, $type]) {
            GridPosition::create(['grid_id' => $grid->id, 'area_name' => $area, 'label' => $area, 'type' => $type]);
        }

        $html = app(GridRenderer::class)->render($grid, $post, $site)['html'];

        $this->assertStringContainsString('<header class="pos-header"', $html);
        $this->assertStringContainsString('<main class="pos-content" id="main-content"', $html);
        $this->assertStringContainsString('<footer class="pos-footer"', $html);
        // exactly one <main> even with both content + main areas present
        $this->assertSame(1, substr_count($html, '<main'));
        // post content publishes inside <article> within the main landmark
        $this->assertMatchesRegularExpression('#<main[^>]*><article>#', $html);
    }

    public function test_hero_background_publishes_as_lcp_image_element(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'mime_type' => 'image/jpeg']);
        $serve = "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve";
        $this->addBlock($page, 'hero', [
            'headline' => 'Big Hero', 'bg_type' => 'image', 'bg_image' => $serve,
            'bg_image_size' => 'cover', 'bg_image_repeat' => 'no-repeat',
        ]);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('object-fit:cover', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('/webp_1600 1600w', $html);
    }

    public function test_hero_fixed_scroll_keeps_css_background(): void
    {
        $site = $this->makeSite();
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $this->addBlock($page, 'hero', [
            'headline' => 'Parallax', 'bg_type' => 'image',
            'bg_image' => 'https://cdn.example.com/bg.jpg', 'bg_scroll_effect' => 'fixed',
        ]);

        $html = $this->build($page, $site);
        $this->assertStringContainsString('background-attachment:fixed', $html);
        $this->assertStringNotContainsString('object-fit:cover;object-position', $html);
    }
}
