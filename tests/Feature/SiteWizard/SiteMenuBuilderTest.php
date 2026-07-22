<?php

namespace Tests\Feature\SiteWizard;

use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\SiteMenuBuilder;
use Tests\TestCase;

/**
 * Menu building from extracted nav: page matching by ref, external links as
 * URL items, and the pages-fallback when a design ships no <nav> at all.
 */
class SiteMenuBuilderTest extends TestCase
{
    private Site $site;
    private SiteMenuBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->builder = app(SiteMenuBuilder::class);
    }

    private function makePage(string $slug): Page
    {
        return Page::factory()->create(['site_id' => $this->site->id, 'slug' => $slug, 'status' => 'draft']);
    }

    private function makeSession(array $attrs): SiteWizardSession
    {
        return SiteWizardSession::create($attrs + [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'status' => 'running',
            'site_id' => $this->site->id,
            'steps' => SiteWizardSession::seedSteps(),
        ]);
    }

    public function test_nav_links_become_page_and_external_items(): void
    {
        $home = $this->makePage('home');
        $about = $this->makePage('about');

        $session = $this->makeSession([
            'source' => 'url',
            'reference_url' => 'https://example.com',
            'sources' => [
                ['ref' => 'https://example.com', 'slug' => 'home', 'is_home' => true, 'status' => 'done', 'page_id' => $home->id],
                ['ref' => 'https://example.com/about', 'slug' => 'about', 'is_home' => false, 'status' => 'done', 'page_id' => $about->id],
            ],
            'nav' => [
                ['label' => 'Home', 'href' => 'https://example.com/'],
                ['label' => 'About', 'href' => 'https://example.com/about'],
                ['label' => 'Missing', 'href' => 'https://example.com/never-built'],
                ['label' => 'Instagram', 'href' => 'https://instagram.com/acme'],
            ],
        ]);

        $menu = $this->builder->build($session, $this->site);

        $this->assertSame('header', $menu->location);
        $items = $menu->items()->orderBy('sort_order')->get();
        $this->assertCount(3, $items); // Missing dropped
        $this->assertSame($home->id, $items[0]->page_id);
        $this->assertSame($about->id, $items[1]->page_id);
        $this->assertSame('https://instagram.com/acme', $items[2]->url);
        $this->assertSame('_blank', $items[2]->target);
    }

    public function test_zip_mode_matches_by_file_path(): void
    {
        $home = $this->makePage('home');
        $contact = $this->makePage('contact');

        $session = $this->makeSession([
            'source' => 'zip',
            'sources' => [
                ['ref' => 'index.html', 'slug' => 'home', 'is_home' => true, 'status' => 'done', 'page_id' => $home->id],
                ['ref' => 'contact.html', 'slug' => 'contact', 'is_home' => false, 'status' => 'done', 'page_id' => $contact->id],
            ],
            'nav' => [
                // The extractor saw these via the loopback static server.
                ['label' => 'Start', 'href' => 'http://127.0.0.1:43121/index.html'],
                ['label' => 'Contact', 'href' => 'http://127.0.0.1:43121/contact.html'],
            ],
        ]);

        $menu = $this->builder->build($session, $this->site);

        $items = $menu->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $items);
        $this->assertSame($home->id, $items[0]->page_id);
        $this->assertSame($contact->id, $items[1]->page_id);
    }

    public function test_empty_nav_falls_back_to_created_pages_home_first(): void
    {
        $about = $this->makePage('about');
        $home = $this->makePage('home');

        $session = $this->makeSession([
            'source' => 'zip',
            'sources' => [
                ['ref' => 'about.html', 'slug' => 'about', 'title' => 'About', 'is_home' => false, 'status' => 'done', 'page_id' => $about->id],
                ['ref' => 'index.html', 'slug' => 'home', 'title' => 'Home', 'is_home' => true, 'status' => 'done', 'page_id' => $home->id],
            ],
            'nav' => [],
        ]);

        $menu = $this->builder->build($session, $this->site);

        $items = $menu->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $items);
        $this->assertSame($home->id, $items[0]->page_id); // home first
    }

    public function test_no_pages_and_no_nav_builds_no_menu(): void
    {
        $session = $this->makeSession(['source' => 'zip', 'sources' => [], 'nav' => []]);

        $this->assertNull($this->builder->build($session, $this->site));
    }
}
