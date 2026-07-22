<?php

namespace Tests\Feature\SiteWizard;

use App\Models\Site;
use App\Services\SiteWizard\SiteWizardMediaImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Media import contract: real image bytes become a library Asset, anything
 * else (non-image, oversized, unreachable, private host) returns null so the
 * caller keeps the original URL instead of dropping the block.
 */
class SiteWizardMediaImporterTest extends TestCase
{
    private Site $site;
    private SiteWizardMediaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        Storage::fake('assets');
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->importer = app(SiteWizardMediaImporter::class);
    }

    /** Smallest valid PNG (1×1 transparent). */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    public function test_real_image_becomes_a_library_asset(): void
    {
        Http::fake(['example.com/*' => Http::response($this->pngBytes(), 200)]);

        $asset = $this->importer->fromUrl($this->site, 'https://example.com/photo.png');

        $this->assertNotNull($asset);
        $this->assertSame($this->site->id, $asset->site_id);
    }

    public function test_repeated_urls_dedupe_by_checksum(): void
    {
        Http::fake(['example.com/*' => Http::response($this->pngBytes(), 200)]);

        $a = $this->importer->fromUrl($this->site, 'https://example.com/a.png');
        $b = $this->importer->fromUrl($this->site, 'https://example.com/b.png');

        $this->assertSame($a->id, $b->id);
    }

    public function test_non_image_bytes_return_null(): void
    {
        Http::fake(['example.com/*' => Http::response('<html>not an image</html>', 200)]);

        $this->assertNull($this->importer->fromUrl($this->site, 'https://example.com/fake.png'));
    }

    public function test_private_hosts_are_refused(): void
    {
        Http::fake();

        $this->assertNull($this->importer->fromUrl($this->site, 'http://127.0.0.1/secret.png'));
        Http::assertNothingSent();
    }

    public function test_local_file_import_works_and_rejects_non_images(): void
    {
        $dir = storage_path('app/site-wizard-test');
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/pic.png", $this->pngBytes());
        file_put_contents("{$dir}/page.html", '<html></html>');

        $this->assertNotNull($this->importer->fromFile($this->site, "{$dir}/pic.png"));
        $this->assertNull($this->importer->fromFile($this->site, "{$dir}/page.html"));
        $this->assertNull($this->importer->fromFile($this->site, "{$dir}/missing.png"));

        @unlink("{$dir}/pic.png");
        @unlink("{$dir}/page.html");
        @rmdir($dir);
    }
}
