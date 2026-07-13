<?php

namespace Tests\Feature\Publishing;

use App\Domain\Assets\Services\AssetService;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AssetService::importFromUrl — external images pulled into the media
 * library (generation-time import): allowlist guard, real-image validation,
 * checksum dedupe, variants + alt on the imported asset.
 */
class AssetImportTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function jpeg(int $w = 1000, int $h = 700): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 90, 120, 90));
        ob_start();
        imagejpeg($im, null, 80);
        imagedestroy($im);

        return ob_get_clean();
    }

    public function test_imports_image_with_variants_alt_and_readable_name(): void
    {
        Http::fake(['loremflickr.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $asset = app(AssetService::class)->importFromUrl(
            $this->site, 'https://loremflickr.com/1200/800/hvac,air?lock=1', 'Hvac Air', 'hvac,air-1'
        );

        $this->assertNotNull($asset);
        $this->assertSame('hvacair-1.jpg', $asset->original_name);
        $this->assertSame('Hvac Air', $asset->alt_text);
        $this->assertSame(['width' => 1000, 'height' => 700], $asset->dimensions);
        $this->assertArrayHasKey('webp_800', $asset->variants);
        $this->assertTrue(Storage::disk('assets')->exists($asset->variants['webp_800']));
    }

    public function test_reimport_dedupes_by_checksum(): void
    {
        Http::fake(['loremflickr.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
        $svc = app(AssetService::class);

        $a = $svc->importFromUrl($this->site, 'https://loremflickr.com/1200/800/a?lock=1');
        $b = $svc->importFromUrl($this->site, 'https://loremflickr.com/1200/800/a?lock=1');

        $this->assertSame($a->id, $b->id);
    }

    public function test_rejects_disallowed_hosts_and_plain_http_without_fetching(): void
    {
        Http::fake();
        $svc = app(AssetService::class);

        $this->assertNull($svc->importFromUrl($this->site, 'https://evil.internal/image.jpg'));
        $this->assertNull($svc->importFromUrl($this->site, 'http://loremflickr.com/1200/800/a'));
        $this->assertNull($svc->importFromUrl($this->site, 'https://169.254.169.254/latest/meta-data'));
        Http::assertNothingSent();
    }

    public function test_rejects_bodies_that_are_not_real_images(): void
    {
        Http::fake(['loremflickr.com/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertNull(app(AssetService::class)->importFromUrl($this->site, 'https://loremflickr.com/x'));
    }

    public function test_returns_null_on_http_failure(): void
    {
        Http::fake(['loremflickr.com/*' => Http::response('', 503)]);

        $this->assertNull(app(AssetService::class)->importFromUrl($this->site, 'https://loremflickr.com/x'));
    }
}
