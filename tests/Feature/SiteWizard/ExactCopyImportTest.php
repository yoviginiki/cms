<?php

namespace Tests\Feature\SiteWizard;

use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\SiteFilesPublisher;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\SitePageExtractor;
use App\Services\SiteWizard\ZipSiteIngestor;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;
use ZipArchive;

/**
 * Exact-copy ZIP imports: each HTML file becomes a page whose raw_html IS the
 * original document; the package's CSS/JS/assets are staged as site files and
 * referenced via serve URLs; links between package pages resolve to the built
 * pages' URLs; publishing returns the document verbatim (no theme wrapper)
 * with site-file references swapped to the static /site-files/ copy.
 */
class ExactCopyImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        config(['queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(ZipSiteIngestor::workspaceBase());
        File::deleteDirectory(base_path(rtrim((string) config('cms.site_files_path'), '/')));
        parent::tearDown();
    }

    private const INDEX_HTML = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<title>Zen Studio — a quiet practice</title>
<link rel="stylesheet" href="zen.css" />
</head>
<body>
<nav><a href="index.html">Home</a> <a href="guide.html#top">Guide</a></nav>
<img src="assets/hero.webp" srcset="assets/hero.webp 800w, assets/hero.webp 400w" alt="" />
<script src="app.js"></script>
<a href="https://example.org/x">external</a>
</body>
</html>
HTML;

    /** Build a session + uploaded ZIP and run the whole pipeline inline. */
    private function runZipBuild(): SiteWizardSession
    {
        // The ingest step reads the entry page in a headless browser — mock it
        // (same approach as SiteWizardFlowTest); exact page builds never use it.
        $extractor = Mockery::mock(SitePageExtractor::class);
        $extractor->shouldReceive('available')->andReturn(true);
        $extractor->shouldReceive('fromLocalFile')->andReturn([
            'manifest' => ['page_title' => 'Zen Studio — a quiet practice', 'design_read' => 'x', 'blocks' => [
                ['kind' => 'heading', 'level' => 1, 'text' => 'Zen Studio'],
            ]],
            'nav' => [
                ['label' => 'Home', 'href' => 'http://127.0.0.1:1/index.html'],
                ['label' => 'Guide', 'href' => 'http://127.0.0.1:1/guide.html'],
            ],
            'links' => [],
            'style' => [],
        ]);
        $this->app->instance(SitePageExtractor::class, $extractor);

        $session = SiteWizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'status' => 'running',
            'source' => 'zip',
            'options' => ['mode' => 'new', 'fidelity' => 'exact', 'max_pages' => 100],
            'steps' => SiteWizardSession::seedSteps(),
        ]);

        $dir = ZipSiteIngestor::workspaceBase() . "/{$session->id}";
        File::ensureDirectoryExists($dir);
        $zip = new ZipArchive();
        $zip->open("{$dir}/upload.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('index.html', self::INDEX_HTML);
        $zip->addFromString('guide.html', "<!DOCTYPE html>\n<html><head><title>The Guide</title></head><body><a href=\"index.html\">back</a></body></html>");
        $zip->addFromString('zen.css', ':root{--pine:#52685A}');
        $zip->addFromString('app.js', 'console.log(1)');
        $zip->addFromString('assets/hero.webp', 'not-really-webp');
        $zip->close();
        $session->update(['workspace_path' => "site-wizard/{$session->id}"]);

        $service = app(\App\Services\SiteWizard\SiteWizardService::class);
        while ($service->runStep($session->refresh())) {
            // one step per iteration, like the queued job chain
        }

        return $session->refresh();
    }

    public function test_zip_build_keeps_documents_verbatim_and_stages_design_files(): void
    {
        $session = $this->runZipBuild();

        $this->assertSame('review', $session->status, (string) $session->error);
        $site = Site::findOrFail($session->site_id);
        $home = Page::where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        // The document survived verbatim: own head/styles/scripts, no blocks.
        $this->assertStringStartsWith('<!DOCTYPE html>', $home->raw_html);
        $this->assertSame(0, $home->blocks()->count());
        $this->assertSame('Zen Studio — a quiet practice', $home->title);

        // Asset refs now point at the site-files serve URLs…
        $this->assertStringContainsString("/api/v1/sites/{$site->id}/files/zen.css", $home->raw_html);
        $this->assertStringContainsString("/api/v1/sites/{$site->id}/files/app.js", $home->raw_html);
        $this->assertStringContainsString("/api/v1/sites/{$site->id}/files/assets/hero.webp 800w", $home->raw_html);
        // …and the files are staged for publishing.
        $this->assertFileExists(SiteFilesPublisher::storageRoot($site) . '/zen.css');
        $this->assertFileExists(SiteFilesPublisher::storageRoot($site) . '/assets/hero.webp');
        $this->assertFileDoesNotExist(SiteFilesPublisher::storageRoot($site) . '/index.html');

        // Cross-page links resolved to the built pages' URLs (fragment kept).
        $this->assertStringContainsString('href="/guide/#top"', $home->raw_html);
        $guide = Page::where('site_id', $site->id)->where('slug', 'guide')->firstOrFail();
        $this->assertStringContainsString('href="/"', $guide->raw_html);

        // External links untouched.
        $this->assertStringContainsString('https://example.org/x', $home->raw_html);

        // The site is marked exact-fidelity (bare-wrapper publishing) and got
        // NO CMS header menu — the design ships its own navigation.
        $this->assertSame('exact', $site->settings['design_fidelity'] ?? null);
        $this->assertSame(0, \App\Models\Menu::where('site_id', $site->id)->count());
    }

    public function test_block_pages_on_exact_sites_publish_bare(): void
    {
        $session = $this->runZipBuild();
        $site = Site::findOrFail($session->site_id);
        $home = Page::where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        // Rebuild the document page as an editable block tree (the men-root
        // pattern): an html-embed section referencing a design file.
        $home->blocks()->create([
            'type' => 'html-embed',
            'order' => 0,
            'level' => 'module',
            'data' => ['html' => '<section class="hero"><img src="/api/v1/sites/' . $site->id . '/files/assets/hero.webp"><h1>Zen</h1></section>'],
        ]);
        $home->update(['raw_html' => null, 'editor_mode' => 'block']);

        $html = app(BuildPageService::class)->build($home->refresh(), $site->theme, $site);

        // Wrapped in the publishing layout — but BARE: no token CSS, no
        // critical CSS, no hardcoded override styles, no skip link. The
        // design package CSS (a compiled @layer sheet) must stay the only CSS.
        $this->assertSame(1, substr_count(strtolower($html), '<html'));
        $this->assertStringNotContainsString('skip-link', $html);
        $this->assertStringNotContainsString('--color-link', $html);
        $this->assertStringNotContainsString('Responsive Navigation', $html);

        // Design-file references rewritten for the static copy on block pages
        // too (the analytics beacon legitimately keeps its API URL).
        $base = '/' . trim($site->deploySlug(), '/');
        $this->assertStringContainsString("{$base}/site-files/assets/hero.webp", $html);
        $this->assertStringNotContainsString("/api/v1/sites/{$site->id}/files/", $html);
    }

    public function test_document_pages_publish_verbatim_without_the_theme_wrapper(): void
    {
        $session = $this->runZipBuild();
        $site = Site::findOrFail($session->site_id);
        $home = Page::where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        $html = app(BuildPageService::class)->build($home, $site->theme, $site);

        // Verbatim document — not wrapped in the publishing layout.
        $this->assertMatchesRegularExpression('/^\s*<!DOCTYPE html>/i', $html);
        $this->assertStringNotContainsString('design-tokens', $html);
        $this->assertSame(1, substr_count(strtolower($html), '<html'));

        // Serve URLs became the static /site-files/ copy, then the slug-hosted
        // base prefix was applied (this site has no custom domain).
        $base = '/' . trim($site->deploySlug(), '/');
        $this->assertStringContainsString("{$base}/site-files/zen.css", $html);
        $this->assertStringNotContainsString('/api/v1/sites/', $html);

        // The staged tree ships into a build when publish() runs.
        $staging = storage_path('framework/testing/exact-copy-staging');
        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging);
        $this->assertSame(3, SiteFilesPublisher::publish($site, $staging));
        $this->assertFileExists("{$staging}/site-files/assets/hero.webp");
        File::deleteDirectory($staging);
    }

    public function test_preview_serves_site_files_and_blocks_traversal(): void
    {
        $session = $this->runZipBuild();
        $site = Site::findOrFail($session->site_id);

        $this->actingAs($this->owner)
            ->get("/api/v1/sites/{$site->id}/files/zen.css")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $this->actingAs($this->owner)
            ->get("/api/v1/sites/{$site->id}/files/..%2F..%2F.env")
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->get("/api/v1/sites/{$site->id}/files/missing.css")
            ->assertNotFound();
    }
}
