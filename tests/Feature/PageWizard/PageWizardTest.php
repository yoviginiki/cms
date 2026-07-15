<?php

namespace Tests\Feature\PageWizard;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageWizard\PageWizardSession;
use App\Models\Site;
use App\Services\PageWizard\PageManifestCompiler;
use App\Services\PageWizard\PageManifestValidator;
use App\Services\PageWizard\PageWizardEngine;
use App\Support\SsrfGuard;
use Mockery;
use Tests\TestCase;

/**
 * Page Wizard: manifest validation + compilation to a real block tree, the
 * service lifecycle (generate → draft page + blocks, nudge re-syncs, accept
 * keeps, abandon deletes) with the AI engine mocked, and the SSRF gate.
 */
class PageWizardTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function sampleManifest(): array
    {
        return [
            'page_title' => 'Acme Landing',
            'design_read' => 'A hero, a features grid, and a closing call to action.',
            'blocks' => [
                ['kind' => 'hero', 'title' => 'Welcome to Acme', 'subtitle' => 'We build things', 'cta_text' => 'Get started', 'cta_url' => 'https://acme.test/start'],
                ['kind' => 'heading', 'text' => 'What we do', 'level' => 'h2'],
                ['kind' => 'columns', 'columns' => [
                    ['heading' => 'Fast', 'body' => 'Really fast.'],
                    ['heading' => 'Simple', 'body' => 'Really simple.'],
                    ['heading' => 'Solid', 'body' => 'Rock solid.'],
                ]],
                ['kind' => 'cta', 'title' => 'Ready?', 'body' => 'Start today.', 'cta_text' => 'Sign up', 'cta_url' => 'https://acme.test/signup'],
            ],
        ];
    }

    public function test_validator_accepts_good_and_rejects_bad(): void
    {
        $v = app(PageManifestValidator::class);
        $this->assertSame([], $v->validate($this->sampleManifest()));

        $this->assertNotEmpty($v->validate(['page_title' => 'x', 'blocks' => []]));                        // empty
        $this->assertNotEmpty($v->validate(['page_title' => 'x', 'blocks' => [['kind' => 'hero']]]));        // hero without title
        $this->assertNotEmpty($v->validate(['page_title' => 'x', 'blocks' => [['kind' => 'image', 'url' => 'javascript:evil()']]])); // unsafe url
        $this->assertNotEmpty($v->validate(['page_title' => 'x', 'blocks' => [['kind' => 'columns', 'columns' => [['heading' => 'only one']]]]])); // <2 cols
    }

    public function test_compiler_builds_a_valid_nested_block_tree(): void
    {
        $tree = app(PageManifestCompiler::class)->compile($this->sampleManifest());

        // Every top-level node is a section → row → column(s) → modules.
        $this->assertCount(4, $tree);
        foreach ($tree as $section) {
            $this->assertSame('section', $section['type']);
            $this->assertSame('row', $section['children'][0]['type']);
            $this->assertSame('column', $section['children'][0]['children'][0]['type']);
        }

        // Hero data mapped correctly.
        $hero = $tree[0]['children'][0]['children'][0]['children'][0];
        $this->assertSame('hero', $hero['type']);
        $this->assertSame('Welcome to Acme', $hero['data']['title']);
        $this->assertSame('https://acme.test/start', $hero['data']['ctaUrl']);

        // Columns block → 3-col row with 3 columns.
        $columnsRow = $tree[2]['children'][0];
        $this->assertSame('1/3+1/3+1/3', $columnsRow['data']['layout']);
        $this->assertCount(3, $columnsRow['children']);

        // CTA → heading + text + button modules in one column.
        $ctaModules = $tree[3]['children'][0]['children'][0]['children'];
        $this->assertSame(['heading', 'text', 'button'], array_column($ctaModules, 'type'));
    }

    public function test_describe_flow_creates_a_draft_page_with_blocks(): void
    {
        $this->mockEngine(fn ($m) => $m->shouldReceive('fromDescription')->once()
            ->andReturn(['manifest' => $this->sampleManifest(), 'usages' => [['input' => 100, 'output' => 200]]]));

        $response = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-describe", [
            'description' => 'A landing page for Acme, a widget company.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'drafting')
            ->assertJsonPath('data.title', 'Acme Landing');

        $pageId = $response->json('data.page.id');
        $this->assertNotNull($pageId);
        $page = Page::find($pageId);
        $this->assertSame('draft', $page->status);
        // Blocks were synced onto the draft page.
        $this->assertGreaterThan(0, Block::where('blockable_type', $page->getMorphClass())->where('blockable_id', $page->id)->count());
        $this->assertStringContainsString("/sites/{$this->site->slug}/{$page->slug}", $response->json('data.preview_path'));
    }

    public function test_nudge_regenerates_and_resyncs_the_same_page(): void
    {
        $revised = $this->sampleManifest();
        $revised['page_title'] = 'Acme — Revised';
        $revised['blocks'][] = ['kind' => 'divider'];

        $this->mockEngine(function ($m) use ($revised) {
            $m->shouldReceive('fromDescription')->once()
                ->andReturn(['manifest' => $this->sampleManifest(), 'usages' => []]);
            $m->shouldReceive('nudge')->once()
                ->andReturn(['manifest' => $revised, 'usages' => [['input' => 50, 'output' => 60]]]);
        });

        $start = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-describe", ['description' => 'Acme landing page please'])->json('data');
        $pageId = $start['page']['id'];

        $nudge = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/{$start['id']}/nudge", [
            'instruction' => 'add a divider at the end',
        ]);

        $nudge->assertOk()->assertJsonPath('data.title', 'Acme — Revised');
        // Same page, re-synced (not a new one).
        $this->assertSame($pageId, $nudge->json('data.page.id'));
        $this->assertSame(1, Page::where('site_id', $this->site->id)->count());
    }

    public function test_accept_keeps_page_and_optionally_publishes(): void
    {
        $this->mockEngine(fn ($m) => $m->shouldReceive('fromDescription')->andReturn(['manifest' => $this->sampleManifest(), 'usages' => []]));

        $start = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-describe", ['description' => 'Acme page'])->json('data');

        $accept = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/{$start['id']}/accept", ['publish' => true]);
        $accept->assertOk()->assertJsonPath('data.page.status', 'published');
        $this->assertSame('accepted', PageWizardSession::find($start['id'])->status);
    }

    public function test_abandon_deletes_the_draft_page(): void
    {
        $this->mockEngine(fn ($m) => $m->shouldReceive('fromDescription')->andReturn(['manifest' => $this->sampleManifest(), 'usages' => []]));

        $start = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-describe", ['description' => 'Acme page'])->json('data');
        $pageId = $start['page']['id'];

        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/{$start['id']}/abandon")->assertOk();

        $this->assertNull(Page::find($pageId));
        $this->assertSame('abandoned', PageWizardSession::find($start['id'])->status);
    }

    public function test_editor_role_cannot_use_wizard(): void
    {
        $this->actingAsEditor()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-describe", ['description' => 'hello there page'])
            ->assertStatus(403);
    }

    public function test_ssrf_gate_blocks_private_and_bad_urls(): void
    {
        foreach (['http://localhost/admin', 'http://127.0.0.1/', 'http://169.254.169.254/latest/meta-data/', 'ftp://example.com', 'http://user:pass@example.com/'] as $bad) {
            try {
                SsrfGuard::assertPublicHttpUrl($bad);
                $this->fail("SSRF gate accepted {$bad}");
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sanitize_drops_invalid_blocks_and_guarantees_title(): void
    {
        $out = app(PageManifestValidator::class)->sanitize(['page_title' => '', 'blocks' => [
            ['kind' => 'hero', 'title' => 'Real'],                          // valid
            ['kind' => 'hero'],                                             // no title → dropped
            ['kind' => 'image', 'url' => 'https://x.test/a.jpg'],           // valid
            ['kind' => 'image', 'url' => 'javascript:x'],                   // unsafe → dropped
            ['kind' => 'bogus'],                                            // unknown → dropped
            ['kind' => 'columns', 'columns' => [['heading' => 'A'], ['heading' => 'B']]], // valid
        ]]);

        $this->assertSame('Imported page', $out['page_title']);
        $this->assertSame(['hero', 'image', 'columns'], array_column($out['blocks'], 'kind'));
    }

    public function test_dom_import_creates_a_free_draft_page(): void
    {
        $manifest = [
            'page_title' => 'Imported Site',
            'design_read' => 'Imported 3 section(s) from example.com.',
            'blocks' => [
                ['kind' => 'hero', 'title' => 'Real Headline From The Site'],
                ['kind' => 'columns', 'columns' => [
                    ['heading' => 'Products', 'image' => 'https://x.test/p.jpg'],
                    ['heading' => 'Locations'],
                    ['heading' => 'Book'],
                ]],
                ['kind' => 'image', 'url' => 'https://x.test/hero.jpg', 'alt' => 'hero'],
            ],
        ];

        $mock = Mockery::mock(\App\Services\PageWizard\PageDomImporter::class);
        $mock->shouldReceive('import')->once()->andReturn($manifest);
        $this->app->instance(\App\Services\PageWizard\PageDomImporter::class, $mock);

        // mode=dom is queued → returns 'capturing'; the UI polls until 'drafting'.
        // (Sync test queue has already run the job by now.)
        $start = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/from-url", [
            'url' => 'https://example.com', 'mode' => 'dom',
        ]);
        $start->assertStatus(201)->assertJsonPath('data.mode', 'dom');

        // Drain the queued CapturePageJob (test env uses the database queue driver).
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $poll = $this->actingAsOwner()->getJson("/api/v1/sites/{$this->site->id}/page-wizard/sessions/{$start->json('data.id')}");
        $poll->assertOk()
            ->assertJsonPath('data.status', 'drafting')
            ->assertJsonPath('data.title', 'Imported Site')
            ->assertJsonPath('data.total_tokens', 0); // FREE — no AI

        $pageId = $poll->json('data.page.id');
        $this->assertNotNull($pageId);
        $this->assertGreaterThan(0, Block::where('blockable_id', $pageId)->count());
    }

    private function mockEngine(callable $expect): void
    {
        $mock = Mockery::mock(PageWizardEngine::class);
        $expect($mock);
        $this->app->instance(PageWizardEngine::class, $mock);
    }
}
