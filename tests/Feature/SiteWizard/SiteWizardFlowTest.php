<?php

namespace Tests\Feature\SiteWizard;

use App\Models\Block;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Models\Theme;
use App\Services\SiteWizard\SitePageExtractor;
use Mockery;
use Tests\TestCase;

/**
 * Site Wizard end to end (extractor mocked, queue sync): a URL build produces
 * a complete native site — Site, extracted Theme set active, draft Pages with
 * real block trees, a header Menu bound to those pages, homepage settings —
 * then accept publishes everything and abandon deletes the whole site.
 */
class SiteWizardFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
    }

    private function extraction(string $title, array $blocks, array $nav = [], array $links = [], array $style = []): array
    {
        return [
            'manifest' => ['page_title' => $title, 'design_read' => 'x', 'blocks' => $blocks],
            'nav' => $nav,
            'links' => $links,
            'style' => $style ?: $this->style(),
        ];
    }

    private function style(): array
    {
        return [
            'title' => 'Acme Studio',
            'body' => ['fontFamily' => 'Georgia, serif', 'fontSize' => '16px', 'color' => 'rgb(40, 40, 46)', 'background' => 'rgb(250, 249, 246)'],
            'h1' => ['fontFamily' => 'Georgia, serif', 'fontWeight' => '700', 'fontSize' => '44px', 'color' => 'rgb(20, 20, 24)'],
            'h2' => ['fontFamily' => 'Georgia, serif', 'fontWeight' => '600', 'fontSize' => '30px'],
            'link_color' => 'rgb(160, 82, 45)',
            'buttons' => [['background' => 'rgb(160, 82, 45)', 'color' => 'rgb(255, 255, 255)', 'radius' => '6px']],
            'background_histogram' => [
                ['color' => 'rgb(250, 249, 246)', 'weight' => 0.8],
                ['color' => 'rgb(244, 241, 234)', 'weight' => 0.2],
            ],
            'shadow_ratio' => 0.05,
            'section_padding' => 96,
            'theme_color_meta' => null,
        ];
    }

    private function mockExtractor(callable $expect): void
    {
        $mock = Mockery::mock(SitePageExtractor::class);
        $expect($mock);
        $this->app->instance(SitePageExtractor::class, $mock);
    }

    /** Drain the queued BuildSiteJob chain (test env runs the database queue driver). */
    private function drainQueue(): void
    {
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
    }

    public function test_url_build_produces_a_complete_site(): void
    {
        $this->mockExtractor(function ($mock) {
            $mock->shouldReceive('fromUrl')->with('https://example.com')->andReturn($this->extraction(
                'Home — Acme Studio',
                [['kind' => 'hero', 'title' => 'Welcome to Acme'], ['kind' => 'text', 'body' => 'We make beautiful things every day.']],
                [
                    ['label' => 'About', 'href' => 'https://example.com/about'],
                    ['label' => 'Contact', 'href' => 'https://example.com/contact'],
                    ['label' => 'Twitter', 'href' => 'https://twitter.com/acme'],
                ],
                ['https://example.com/about', 'https://example.com/contact'],
            ));
            $mock->shouldReceive('fromUrl')->with('https://example.com/about')->andReturn($this->extraction(
                'About — Acme Studio',
                [['kind' => 'heading', 'text' => 'About us', 'level' => 'h2'], ['kind' => 'text', 'body' => 'A long story about the studio and its people.']],
            ));
            $mock->shouldReceive('fromUrl')->with('https://example.com/contact')->andReturn($this->extraction(
                'Contact — Acme Studio',
                [['kind' => 'heading', 'text' => 'Say hello', 'level' => 'h2'], ['kind' => 'text', 'body' => 'Write to hello@acme.example and we reply fast.']],
            ));
        });

        $start = $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com',
        ]);
        $start->assertStatus(201);
        $id = $start->json('data.id');
        $this->drainQueue();
        $poll = $this->actingAsOwner()->getJson("/api/v1/site-wizard/sessions/{$id}");
        $poll->assertOk()->assertJsonPath('data.status', 'review');

        $session = SiteWizardSession::findOrFail($id);
        $site = Site::findOrFail($session->site_id);

        // Site named from the entry <title>, brandiest half kept.
        $this->assertSame('Acme Studio', $site->name);

        // Theme extracted from the styles and set active (not the createSite default).
        $theme = Theme::findOrFail($site->active_theme_id);
        $this->assertNotSame('Default', $theme->name);
        $this->assertSame('Site Wizard', $theme->manifest_json['author'] ?? null);
        $this->assertNotEmpty($theme->document);

        // Three draft pages with real block trees; home got slug 'home'.
        $pages = Page::where('site_id', $site->id)->get();
        $this->assertCount(3, $pages);
        $this->assertTrue($pages->every(fn ($p) => $p->status === 'draft'));
        $home = $pages->firstWhere('slug', 'home');
        $this->assertNotNull($home);
        $this->assertGreaterThan(0, Block::where('blockable_id', $home->id)->count());

        // Header menu: 2 page items + 1 external, in nav order.
        $menu = Menu::findOrFail($session->menu_id);
        $this->assertSame('header', $menu->location);
        $items = $menu->items()->orderBy('sort_order')->get();
        $this->assertCount(3, $items);
        $this->assertNotNull($items[0]->page_id);
        $this->assertSame('Twitter', $items[2]->label);
        $this->assertSame('https://twitter.com/acme', $items[2]->url);

        // Homepage assignment.
        $this->assertSame($home->id, $site->fresh()->settings['homepage_id'] ?? null);
        $this->assertSame('page', $site->fresh()->settings['homepage_type'] ?? null);

        // Accept publishes everything.
        $this->actingAsOwner()->postJson("/api/v1/site-wizard/sessions/{$id}/accept")->assertOk();
        $this->assertTrue(Page::where('site_id', $site->id)->get()->every(fn ($p) => $p->status === 'published'));
        $this->assertSame('accepted', $session->fresh()->status);
    }

    public function test_abandon_deletes_the_whole_site(): void
    {
        $this->mockExtractor(function ($mock) {
            $mock->shouldReceive('fromUrl')->andReturn($this->extraction(
                'Tiny Site',
                [['kind' => 'hero', 'title' => 'Just one page']],
            ));
        });

        $start = $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com', 'max_pages' => 1,
        ]);
        $id = $start->json('data.id');
        $this->drainQueue();
        $siteId = SiteWizardSession::findOrFail($id)->site_id;
        $this->assertNotNull($siteId);

        $this->actingAsOwner()->postJson("/api/v1/site-wizard/sessions/{$id}/abandon")->assertOk();
        $this->assertNull(Site::find($siteId)); // soft-deleted, gone from normal queries
        $this->assertSame('abandoned', SiteWizardSession::findOrFail($id)->status);
    }

    public function test_ssrf_urls_are_rejected_up_front(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'http://127.0.0.1/internal',
        ])->assertStatus(422);

        $this->assertSame(0, SiteWizardSession::count());
    }

    public function test_a_failing_page_does_not_sink_the_build(): void
    {
        $this->mockExtractor(function ($mock) {
            $mock->shouldReceive('fromUrl')->with('https://example.com')->andReturn($this->extraction(
                'Multi',
                [['kind' => 'hero', 'title' => 'Hello there world']],
                [['label' => 'Broken', 'href' => 'https://example.com/broken']],
            ));
            $mock->shouldReceive('fromUrl')->with('https://example.com/broken')
                ->andThrow(new \RuntimeException('Could not read that page.'));
        });

        $start = $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com',
        ]);
        $id = $start->json('data.id');
        $this->drainQueue();

        $session = SiteWizardSession::findOrFail($id);
        $this->assertSame('review', $session->status);

        $sources = collect($session->sources);
        $this->assertSame('done', $sources->firstWhere('is_home', true)['status']);
        $this->assertSame('failed', $sources->firstWhere('slug', 'broken')['status']);
        $this->assertCount(1, $session->page_ids);
    }

    public function test_sessions_are_tenant_scoped(): void
    {
        $this->mockExtractor(function ($mock) {
            $mock->shouldReceive('fromUrl')->andReturn($this->extraction('S', [['kind' => 'hero', 'title' => 'Scoped page here']]));
        });

        $start = $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com', 'max_pages' => 1,
        ]);
        $id = $start->json('data.id');
        $this->drainQueue();

        $stranger = \App\Models\User::factory()->owner()->create([
            'tenant_id' => \App\Models\Tenant::factory()->create()->id,
        ]);
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/site-wizard/sessions/{$id}")
            ->assertStatus(404);
    }
}
