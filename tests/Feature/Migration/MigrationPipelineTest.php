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

    public function test_extractor_preserves_accordions_and_column_structure(): void
    {
        // the exact Divi shape that was being flattened: one row, two columns —
        // left: heading + accordion (first toggle open) + button; right: image + text
        $html = <<<'HTML'
<html><body><div class="entry-content">
<div class="et_pb_section et_pb_section_1">
  <div class="et_pb_row et_pb_row_2">
    <div class="et_pb_column et_pb_column_1_2 et_pb_column_3">
      <div class="et_pb_module et_pb_text"><div class="et_pb_text_inner"><h2>За метода</h2></div></div>
      <div class="et_pb_module et_pb_accordion et_pb_accordion_0">
        <div class="et_pb_toggle et_pb_accordion_item et_pb_toggle_open">
          <h5 class="et_pb_toggle_title">Какво означава "Хейко"</h5>
          <div class="et_pb_toggle_content clearfix"><p>Първо съдържание за баланса.</p></div>
        </div>
        <div class="et_pb_toggle et_pb_accordion_item et_pb_toggle_close">
          <h5 class="et_pb_toggle_title">Защо да избера тази дзен терапия?</h5>
          <div class="et_pb_toggle_content clearfix"><p>Второ съдържание с <a href="https://origin.tld/kakvo-e-zen/">линк</a>.</p></div>
        </div>
      </div>
      <div class="et_pb_button_module_wrapper"><a class="et_pb_button" href="https://origin.tld/za-terapiata/">Прочети повече</a></div>
    </div>
    <div class="et_pb_column et_pb_column_1_2 et_pb_column_4">
      <div class="et_pb_module et_pb_image"><span class="et_pb_image_wrap"><img src="https://origin.tld/up/photo.jpg" alt=""></span></div>
      <div class="et_pb_module et_pb_text"><div class="et_pb_text_inner"><p>Казвам се Николай Петров и това е достатъчно дълъг текст.</p></div></div>
    </div>
  </div>
</div>
</div></body></html>
HTML;

        $result = app(LiveContentExtractor::class)->extract($html, 'Начало', null);

        $this->assertCount(1, $result['blocks']);
        $row = $result['blocks'][0];
        $this->assertSame('_columns', $row['type']);
        $this->assertCount(2, $row['columns']);

        [$left, $right] = $row['columns'];
        $this->assertSame(['heading', 'accordion', 'button'], array_column($left, 'type'));

        $accordion = $left[1];
        $this->assertTrue($accordion['data']['openFirst']);
        $this->assertCount(2, $accordion['data']['items']);
        $this->assertSame('Какво означава "Хейко"', $accordion['data']['items'][0]['title']);
        $this->assertStringContainsString('Първо съдържание', $accordion['data']['items'][0]['content']);
        // links inside accordion bodies survive
        $this->assertStringContainsString('href="https://origin.tld/kakvo-e-zen/"', $accordion['data']['items'][1]['content']);

        $this->assertSame('Прочети повече', $left[2]['data']['text']);
        $this->assertSame(['image', 'paragraph'], array_column($right, 'type'));
    }

    public function test_extractor_maps_divi_tabs_to_an_accordion(): void
    {
        // Tabbed panels can't render statically, so tabs → accordion (every
        // panel's title + content stays crawlable, not just the first tab).
        $html = <<<'HTML'
<html><body><div class="entry-content">
<div class="et_pb_module et_pb_tabs et_pb_tabs_0">
  <ul class="et_pb_tabs_controls">
    <li class="et_pb_tab_active"><a href="#">Overview</a></li>
    <li><a href="#">Details</a></li>
  </ul>
  <div class="et_pb_all_tabs">
    <div class="et_pb_tab et_pb_active_content"><div class="et_pb_tab_content"><p>First panel body.</p></div></div>
    <div class="et_pb_tab"><div class="et_pb_tab_content"><p>Second panel with a <a href="https://origin.tld/more/">link</a>.</p></div></div>
  </div>
</div>
</div></body></html>
HTML;

        $result = app(LiveContentExtractor::class)->extract($html, 'Page', null);

        $this->assertCount(1, $result['blocks']);
        $acc = $result['blocks'][0];
        $this->assertSame('accordion', $acc['type']);
        $this->assertTrue($acc['data']['openFirst']);
        $this->assertCount(2, $acc['data']['items']);
        $this->assertSame('Overview', $acc['data']['items'][0]['title']);
        $this->assertStringContainsString('First panel body.', $acc['data']['items'][0]['content']);
        $this->assertSame('Details', $acc['data']['items'][1]['title']);
        $this->assertStringContainsString('href="https://origin.tld/more/"', $acc['data']['items'][1]['content']);
    }

    public function test_extractor_maps_a_gallery_preferring_full_size_links(): void
    {
        $html = <<<'HTML'
<html><body><div class="entry-content">
<div class="et_pb_module et_pb_gallery">
  <div class="et_pb_gallery_items">
    <div class="et_pb_gallery_item"><a href="https://origin.tld/up/full-a.jpg"><img src="https://origin.tld/up/full-a-400x300.jpg" alt="Alpha"></a></div>
    <div class="et_pb_gallery_item"><a href="https://origin.tld/up/full-b.jpg"><img src="https://origin.tld/up/full-b-400x300.jpg" alt="Beta"></a></div>
  </div>
</div>
</div></body></html>
HTML;

        $result = app(LiveContentExtractor::class)->extract($html, 'Page', null);

        $this->assertCount(1, $result['blocks']);
        $gallery = $result['blocks'][0];
        $this->assertSame('gallery', $gallery['type']);
        $this->assertCount(2, $gallery['data']['images']);
        // canonical gallery shape = URL strings; the lightbox full-size target
        // wins over the rendered thumbnail
        $this->assertSame('https://origin.tld/up/full-a.jpg', $gallery['data']['images'][0]);
        $this->assertSame('https://origin.tld/up/full-b.jpg', $gallery['data']['images'][1]);
    }

    public function test_extractor_maps_number_counters_to_stats(): void
    {
        $html = <<<'HTML'
<html><body><div class="entry-content">
<div class="et_pb_module et_pb_counters">
  <div class="et_pb_number_counter" data-number-value="95">
    <div class="percent"><p><span class="percent-value">0</span><span class="percent-sign">%</span></p></div>
    <h3 class="title">Satisfaction</h3>
  </div>
  <div class="et_pb_number_counter" data-number-value="1200">
    <div class="percent"><p><span class="percent-value">0</span></p></div>
    <h3 class="title">Clients</h3>
  </div>
</div>
</div></body></html>
HTML;

        $result = app(LiveContentExtractor::class)->extract($html, 'Page', null);

        $this->assertCount(1, $result['blocks']);
        $stats = $result['blocks'][0];
        $this->assertSame('stats', $stats['type']);
        $this->assertCount(2, $stats['data']['items']);
        $this->assertSame('95', $stats['data']['items'][0]['value']);
        $this->assertSame('%', $stats['data']['items'][0]['suffix']);
        $this->assertSame('Satisfaction', $stats['data']['items'][0]['label']);
        $this->assertSame('1200', $stats['data']['items'][1]['value']);
        $this->assertSame('Clients', $stats['data']['items'][1]['label']);
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
