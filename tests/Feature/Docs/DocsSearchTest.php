<?php

namespace Tests\Feature\Docs;

use App\Http\Controllers\DocsController;
use Tests\TestCase;

class DocsSearchTest extends TestCase
{
    public function test_search_finds_footer_guide_first_and_links_to_heading_anchor(): void
    {
        $results = app(DocsController::class)->searchDocs('global footer');

        $this->assertNotEmpty($results);
        $this->assertSame('GUIDE-HEADER-FOOTER', $results[0]['slug']);
        $this->assertNotEmpty($results[0]['hits']);
        $this->assertStringContainsString('<mark>', $results[0]['hits'][0]['html']);

        // Every anchor a result links to exists as an id in the rendered doc.
        $page = $this->get('/docs/GUIDE-HEADER-FOOTER')->assertOk()->getContent();
        foreach ($results[0]['hits'] as $hit) {
            if ($hit['anchor']) {
                $this->assertStringContainsString('id="' . $hit['anchor'] . '"', $page);
            }
        }
    }

    public function test_all_terms_must_match_and_cyrillic_is_case_insensitive(): void
    {
        $controller = app(DocsController::class);

        $this->assertSame([], $controller->searchDocs('footer zzqqxxnotaword'));
        $this->assertSame([], $controller->searchDocs('a'));
        $this->assertSame(
            array_column($controller->searchDocs('футър'), 'slug'),
            array_column($controller->searchDocs('ФУТЪР'), 'slug'),
        );
    }

    public function test_search_page_renders_and_escapes_query(): void
    {
        $this->get('/docs/search?q=footer')
            ->assertOk()
            ->assertSee('GUIDE-HEADER-FOOTER.md')
            ->assertSee('name="q"', false);

        $this->get('/docs/search?q=' . urlencode('<script>x</script>'))
            ->assertOk()
            ->assertDontSee('<script>x</script>', false);
    }

    public function test_code_fence_comments_are_not_rendered_as_headings(): void
    {
        // README has a fenced bash block with "# ..." comment lines.
        $page = $this->get('/docs/GUIDE-HEADER-FOOTER')->assertOk()->getContent();
        $this->assertStringNotContainsString('<h1 id="settings-json', $page);
    }

    public function test_md_suffixed_links_redirect_and_unknown_docs_404(): void
    {
        $this->get('/docs/GUIDE-MENUS.md')->assertRedirect('/docs/GUIDE-MENUS');
        $this->get('/docs/no-such-doc')->assertNotFound();
    }
}
