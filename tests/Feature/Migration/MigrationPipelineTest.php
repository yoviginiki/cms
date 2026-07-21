<?php

namespace Tests\Feature\Migration;

use App\Domain\Migration\Services\LinkRewriter;
use App\Domain\Migration\Services\LiveContentExtractor;
use App\Domain\Migration\Services\MigrationDiffChecker;
use App\Domain\Migration\Services\RedirectMapGenerator;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The migration pipeline: spider extraction, internal-link recreation,
 * SEO-preserving redirect maps, and the forensic origin-vs-migrated diff.
 */
class MigrationPipelineTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeContent(): array
    {
        $cat = Category::factory()->create(['site_id' => $this->site->id, 'slug' => 'zen', 'name' => 'Дзен']);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'za-kontakt', 'title' => 'За контакт']);
        $post = Post::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'kakvo-e-zen', 'title' => 'Какво е Дзен', 'category_id' => $cat->id]);

        return [$cat, $page, $post];
    }

    public function test_extractor_builds_blocks_and_meta_from_rendered_html(): void
    {
        $html = <<<'HTML'
<html><head>
<title>Тест страница - Site</title>
<meta name="description" content="Описание за SEO.">
<meta property="og:image" content="https://origin.tld/up/2020/12/photo-300x200.jpg">
</head><body>
<div class="entry-content">
  <h1>Тест страница</h1>
  <h2>Подзаглавие</h2>
  <p>Първи параграф с <a href="https://origin.tld/kakvo-e-zen/">вътрешен линк</a>.</p>
  <ul><li>Точка едно</li><li>Точка две</li></ul>
  <img src="https://origin.tld/up/2020/12/photo-300x200.jpg" alt="Снимка">
  <div class="et_pb_sidebar"><p>Sidebar junk to skip</p></div>
</div>
</body></html>
HTML;

        $result = app(LiveContentExtractor::class)->extract($html, 'Тест страница', null);

        $types = array_column($result['blocks'], 'type');
        $this->assertSame(['heading', 'paragraph', 'paragraph', 'image'], $types);
        // the duplicated page-title h1 is dropped; h2 survives
        $this->assertSame('Подзаглавие', $result['blocks'][0]['data']['text']);
        // inline links survive extraction (internal-link recreation depends on it)
        $this->assertStringContainsString('href="https://origin.tld/kakvo-e-zen/"', $result['blocks'][1]['data']['content']);
        // list is kept whole
        $this->assertStringContainsString('<li>Точка едно</li>', $result['blocks'][2]['data']['content']);
        // sidebar content is skipped
        foreach ($result['blocks'] as $b) {
            $this->assertStringNotContainsString('Sidebar junk', json_encode($b, JSON_UNESCAPED_UNICODE));
        }
        $this->assertSame('Описание за SEO.', $result['meta']['description']);
        $this->assertSame('https://origin.tld/up/2020/12/photo-300x200.jpg', $result['meta']['og_image']);
    }

    public function test_link_rewriter_recreates_internal_links(): void
    {
        $this->makeContent();

        $rewriter = app(LinkRewriter::class)->buildMap($this->site);
        $html = '<p><a href="https://origin.tld/kakvo-e-zen/?swcfpc=1">пост</a> и '
            . '<a href="/за-контакт/">контакт</a> и '
            . '<a href="https://external.example/x">външен</a> и '
            . '<a href="https://origin.tld/nqma-takava/">няма</a></p>';

        $out = $rewriter->rewriteHtml($html, 'origin.tld');

        $this->assertStringContainsString('href="/zen/kakvo-e-zen/"', $out); // post → category-prefixed
        $this->assertStringContainsString('href="/za-kontakt/"', $out);     // Cyrillic path resolved
        $this->assertStringContainsString('href="https://external.example/x"', $out);
        $this->assertStringContainsString('href="https://origin.tld/nqma-takava/"', $out); // untouched
        $this->assertContains('/nqma-takava/', $rewriter->unresolved());
    }

    public function test_redirect_map_covers_origin_urls_and_both_server_dialects(): void
    {
        $this->makeContent();

        Http::fake([
            'origin.tld/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                . '<sitemap><loc>https://origin.tld/post-sitemap.xml</loc></sitemap>'
                . '<sitemap><loc>https://origin.tld/page-sitemap.xml</loc></sitemap>'
                . '</sitemapindex>'
            ),
            'origin.tld/post-sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                . '<url><loc>https://origin.tld/kakvo-e-zen/</loc></url>'
                . '<url><loc>https://origin.tld/izcheznal-post/</loc></url>'
                . '</urlset>'
            ),
            'origin.tld/page-sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                . '<url><loc>https://origin.tld/%D0%B7%D0%B0-%D0%BA%D0%BE%D0%BD%D1%82%D0%B0%D0%BA%D1%82/</loc></url>'
                . '</urlset>'
            ),
        ]);

        $result = app(RedirectMapGenerator::class)->generate($this->site, 'https://origin.tld');

        // the post URL changes shape (/{slug}/ → /{category}/{slug}/) — redirect required
        $this->assertSame('/zen/kakvo-e-zen/', $result['mapped']['/kakvo-e-zen/']);
        // Cyrillic page path decoded and mapped
        $this->assertSame('/za-kontakt/', $result['mapped']['/за-контакт/']);
        // WP /category/{slug}/ convention covered
        $this->assertSame('/zen/', $result['mapped']['/category/zen/']);
        // unmigrated origin URL is reported, not silently dropped
        $this->assertContains('/izcheznal-post/', $result['unmapped']);

        $this->assertStringContainsString('Redirect 301 "/kakvo-e-zen" "/zen/kakvo-e-zen/"', $result['htaccess']);
        $this->assertStringContainsString('location = "/kakvo-e-zen" { return 301 "/zen/kakvo-e-zen/"; }', $result['nginx']);
    }

    public function test_diff_checker_flags_missing_elements(): void
    {
        $this->makeContent();

        $originHtml = <<<'HTML'
<html><head><title>Оригинал</title><meta name="description" content="desc"></head>
<body><style>.hero{background-image:url('https://origin.tld/up/lily-1920.jpg');}</style>
<div class="entry-content">
  <h2>Запазено заглавие</h2>
  <h2>Липсващо заглавие</h2>
  <p>Това изречение е достатъчно дълго и присъства и в двете страници напълно.</p>
  <p>Това дълго изречение съществува само в оригинала и трябва да бъде докладвано.</p>
  <img src="https://origin.tld/up/present-600x400.jpg">
  <img src="https://origin.tld/up/missing.jpg">
  <a href="https://origin.tld/kakvo-e-zen/">линк към пост</a>
</div></body></html>
HTML;

        $newHtml = <<<'HTML'
<html><head><title>Ново</title></head><body>
<main>
  <h2>Запазено заглавие</h2>
  <p>Това изречение е достатъчно дълго и присъства и в двете страници напълно.</p>
  <img src="/assets/files/present.jpg">
</main></body></html>
HTML;

        Http::fake([
            'origin.tld/page' => Http::response($originHtml),
            'new.tld/page' => Http::response($newHtml),
        ]);

        $report = app(MigrationDiffChecker::class)->comparePairs($this->site, 'origin.tld', [
            ['origin' => 'https://origin.tld/page', 'new' => 'https://new.tld/page', 'label' => 'p'],
        ]);

        $page = $report['pages'][0];
        $this->assertContains('Липсващо заглавие', $page['missing_headings']);
        $this->assertNotContains('Запазено заглавие', $page['missing_headings']);
        $this->assertContains('missing.jpg', $page['missing_images']);
        $this->assertNotContains('present.jpg', $page['missing_images']);
        $this->assertContains('lily-1920.jpg', $page['missing_background_images']);
        $this->assertNotEmpty(array_filter($page['missing_links'], fn ($l) => str_contains($l, '/zen/kakvo-e-zen/')));
        $this->assertLessThan(100.0, $page['text_coverage']);
        $this->assertGreaterThan(0.0, $page['text_coverage']);
    }
}
