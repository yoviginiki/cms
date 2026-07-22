<?php

namespace Tests\Feature\SiteWizard;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\SitePageExtractor;
use Mockery;
use Tests\TestCase;

/**
 * 'into' mode: the wizard imports pages + ONE submenu into an EXISTING site
 * and touches nothing else — theme, homepage, and the site's own menu items
 * stay exactly as they were, and discard removes exactly what was added.
 */
class SiteWizardIntoSiteTest extends TestCase
{
    private Site $target;
    private Menu $headerMenu;
    private Page $existingPage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        config(['queue.default' => 'sync']);

        $this->target = Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => ['homepage_id' => 'keep-me', 'homepage_type' => 'page'],
        ]);
        $this->existingPage = Page::factory()->create(['site_id' => $this->target->id, 'slug' => 'welcome']);
        $this->headerMenu = Menu::create([
            'site_id' => $this->target->id, 'name' => 'Main', 'slug' => 'main', 'location' => 'header',
        ]);
        MenuItem::create(['menu_id' => $this->headerMenu->id, 'label' => 'Welcome', 'page_id' => $this->existingPage->id, 'sort_order' => 0]);
    }

    private function extraction(string $title, array $blocks, array $nav = []): array
    {
        return [
            'manifest' => ['page_title' => $title, 'design_read' => 'x', 'blocks' => $blocks],
            'nav' => $nav,
            'links' => [],
            'style' => [],
        ];
    }

    private function mockExtractor(callable $expect): void
    {
        $mock = Mockery::mock(SitePageExtractor::class);
        $expect($mock);
        $this->app->instance(SitePageExtractor::class, $mock);
    }

    private function startInto(): string
    {
        $this->mockExtractor(function ($mock) {
            $mock->shouldReceive('fromUrl')->with('https://example.com')->andReturn($this->extraction(
                'Retreat Home',
                [['kind' => 'hero', 'title' => 'Welcome to the retreat']],
                [['label' => 'Program', 'href' => 'https://example.com/program']],
            ));
            $mock->shouldReceive('fromUrl')->with('https://example.com/program')->andReturn($this->extraction(
                'Program',
                [['kind' => 'heading', 'text' => 'The program', 'level' => 'h2'], ['kind' => 'text', 'body' => 'A full week of quiet mornings and long walks.']],
            ));
        });

        $start = $this->actingAsOwner()->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com',
            'site_id' => $this->target->id,
            'menu_label' => 'Retreat 2026',
        ]);
        $start->assertStatus(201)->assertJsonPath('data.mode', 'into');

        return $start->json('data.id');
    }

    public function test_into_mode_adds_pages_and_a_submenu_without_touching_the_site(): void
    {
        $sitesBefore = Site::count();
        $themeBefore = $this->target->active_theme_id;

        $id = $this->startInto();

        $session = SiteWizardSession::findOrFail($id);
        $this->assertSame('review', $session->status);

        // No new site; theme + homepage untouched.
        $this->assertSame($sitesBefore, Site::count());
        $this->assertSame($themeBefore, $this->target->fresh()->active_theme_id);
        $this->assertSame('keep-me', $this->target->fresh()->settings['homepage_id']);

        // create_site + theme steps skipped, pages built with prefixed slugs.
        $this->assertSame('skipped', $session->stepState('create_site')['status']);
        $this->assertSame('skipped', $session->stepState('theme')['status']);
        $pages = Page::whereIn('id', $session->page_ids)->get();
        $this->assertCount(2, $pages);
        $this->assertNotNull($pages->firstWhere('slug', 'retreat-2026'));          // home → prefix itself
        $this->assertNotNull($pages->firstWhere('slug', 'retreat-2026-program'));

        // One new parent item in the EXISTING header menu, submenu nested under it.
        $parent = MenuItem::find($session->menu_item_id);
        $this->assertSame($this->headerMenu->id, $parent->menu_id);
        $this->assertSame('Retreat 2026', $parent->label);
        $this->assertNull($parent->parent_id);
        $children = MenuItem::where('parent_id', $parent->id)->get();
        $this->assertGreaterThan(0, $children->count());
        // The site's own item is untouched.
        $this->assertSame(1, MenuItem::where('menu_id', $this->headerMenu->id)->where('label', 'Welcome')->count());
    }

    public function test_abandon_removes_only_what_the_import_added(): void
    {
        $id = $this->startInto();
        $session = SiteWizardSession::findOrFail($id);
        $importedPageIds = $session->page_ids;
        $parentId = $session->menu_item_id;

        $this->actingAsOwner()->postJson("/api/v1/site-wizard/sessions/{$id}/abandon")->assertOk();

        $this->assertNotNull(Site::find($this->target->id));
        $this->assertNotNull(Page::find($this->existingPage->id));
        $this->assertSame(0, Page::whereIn('id', $importedPageIds)->count());
        $this->assertNull(MenuItem::find($parentId));
        $this->assertSame(0, MenuItem::where('parent_id', $parentId)->count());
        $this->assertSame(1, MenuItem::where('menu_id', $this->headerMenu->id)->count()); // just 'Welcome'
    }

    public function test_accept_publishes_the_imported_pages_only(): void
    {
        $id = $this->startInto();
        $session = SiteWizardSession::findOrFail($id);

        $this->actingAsOwner()->postJson("/api/v1/site-wizard/sessions/{$id}/accept")->assertOk();

        $this->assertTrue(Page::whereIn('id', $session->page_ids)->get()->every(fn ($p) => $p->status === 'published'));
        $this->assertSame('keep-me', $this->target->fresh()->settings['homepage_id']);
    }

    public function test_cannot_import_into_a_foreign_site(): void
    {
        $foreignTenant = \App\Models\Tenant::factory()->create();
        $stranger = \App\Models\User::factory()->owner()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($stranger, 'sanctum')->postJson('/api/v1/site-wizard/sessions/from-url', [
            'url' => 'https://example.com',
            'site_id' => $this->target->id,
        ])->assertStatus(404);
    }
}
