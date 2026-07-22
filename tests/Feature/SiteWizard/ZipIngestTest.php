<?php

namespace Tests\Feature\SiteWizard;

use App\Models\SiteWizard\SiteWizardSession;
use App\Services\SiteWizard\ZipSiteIngestor;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * ZIP ingest security + page discovery: zip-slip rejection, symlink/extension
 * filtering, uncompressed-size bomb guard, Canva-style root-folder unwrap,
 * and homepage detection.
 */
class ZipIngestTest extends TestCase
{
    private ZipSiteIngestor $ingestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->ingestor = app(ZipSiteIngestor::class);
    }

    protected function tearDown(): void
    {
        // The SANDBOXED base only — never the production workspace dir.
        File::deleteDirectory(ZipSiteIngestor::workspaceBase());
        parent::tearDown();
    }

    private function makeSession(): SiteWizardSession
    {
        return SiteWizardSession::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'status' => 'running',
            'source' => 'zip',
            'steps' => SiteWizardSession::seedSteps(),
        ]);
    }

    /** @param array<string,string> $entries name => content */
    private function makeZip(SiteWizardSession $session, array $entries): void
    {
        $dir = ZipSiteIngestor::workspaceBase() . "/{$session->id}";
        File::ensureDirectoryExists($dir);
        $zip = new ZipArchive();
        $zip->open("{$dir}/upload.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $session->update(['workspace_path' => "site-wizard/{$session->id}"]);
    }

    public function test_extracts_pages_and_detects_the_homepage(): void
    {
        $session = $this->makeSession();
        $this->makeZip($session, [
            'index.html' => '<html><title>Home</title></html>',
            'about.html' => '<html><title>About</title></html>',
            'css/style.css' => 'body{}',
            'assets/logo.png' => 'fakepng',
        ]);

        $result = $this->ingestor->extract($session);

        $this->assertCount(2, $result['pages']);
        $home = collect($result['pages'])->firstWhere('is_home', true);
        $this->assertSame('index.html', $home['path']);
        $this->assertSame('home', $home['slug']);
        $this->assertSame('about', collect($result['pages'])->firstWhere('is_home', false)['slug']);
        $this->assertFileExists($result['root'] . '/css/style.css');
    }

    public function test_unwraps_single_root_folder_archives(): void
    {
        $session = $this->makeSession();
        $this->makeZip($session, [
            'my-canva-site/index.html' => '<html></html>',
            'my-canva-site/style.css' => 'body{}',
        ]);

        $result = $this->ingestor->extract($session);

        $this->assertStringEndsWith('/my-canva-site', $result['root']);
        $this->assertSame('index.html', $result['pages'][0]['path']);
    }

    public function test_rejects_zip_slip_entries(): void
    {
        $session = $this->makeSession();
        $this->makeZip($session, [
            'index.html' => '<html></html>',
            '../../evil.html' => 'pwned',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unsafe file path');
        $this->ingestor->extract($session);
    }

    public function test_skips_disallowed_extensions(): void
    {
        $session = $this->makeSession();
        $this->makeZip($session, [
            'index.html' => '<html></html>',
            'shell.php' => '<?php evil();',
            'run.sh' => 'rm -rf /',
        ]);

        $result = $this->ingestor->extract($session);

        $this->assertFileDoesNotExist($result['root'] . '/shell.php');
        $this->assertFileDoesNotExist($result['root'] . '/run.sh');
        $this->assertFileExists($result['root'] . '/index.html');
    }

    public function test_uncompressed_size_bomb_is_rejected_before_extraction(): void
    {
        config(['cms.site_wizard.zip_max_uncompressed_mb' => 1]);
        $session = $this->makeSession();
        // 4 × 500KB of highly-compressible data → >1MB uncompressed.
        $this->makeZip($session, [
            'index.html' => '<html></html>',
            'a.txt' => str_repeat('a', 500 * 1024),
            'b.txt' => str_repeat('b', 500 * 1024),
            'c.txt' => str_repeat('c', 500 * 1024),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too large');
        $this->ingestor->extract($session);
    }

    public function test_archive_without_html_pages_is_rejected(): void
    {
        $session = $this->makeSession();
        $this->makeZip($session, ['style.css' => 'body{}']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No HTML pages');
        $this->ingestor->extract($session);
    }
}
