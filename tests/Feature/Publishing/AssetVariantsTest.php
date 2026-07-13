<?php

namespace Tests\Feature\Publishing;

use App\Domain\Assets\Services\AssetService;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Asset pipeline §9 — WebP/responsive variants: generation at upload (real
 * GD images end-to-end), backfill for legacy assets, and publish-time
 * variant resolution/copying.
 */
class AssetVariantsTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_upload_generates_responsive_and_webp_variants(): void
    {
        $response = $this->actingAsOwner()->post(
            "/api/v1/sites/{$this->site->id}/assets",
            ['file' => UploadedFile::fake()->image('photo.jpg', 1200, 900)],
            $this->apiHeaders()
        );
        $response->assertStatus(201);

        $asset = Asset::where('site_id', $this->site->id)->firstOrFail();
        $variants = $asset->variants;

        foreach (['thumb_200', 'small_400', 'webp_400', 'medium_800', 'webp_800'] as $key) {
            $this->assertArrayHasKey($key, $variants, "missing variant {$key}");
            $this->assertTrue(Storage::disk('assets')->exists($variants[$key]), "file missing for {$key}");
            $this->assertGreaterThan(0, Storage::disk('assets')->size($variants[$key]), "empty file for {$key}");
        }
        // 1200px wide → no 1600 tier
        $this->assertArrayNotHasKey('large_1600', $variants);
        $this->assertSame(['width' => 1200, 'height' => 900], $asset->dimensions);

        // WebP variants are real WebP files
        $webp = Storage::disk('assets')->get($variants['webp_800']);
        $this->assertSame('RIFF', substr($webp, 0, 4));
        $this->assertSame('WEBP', substr($webp, 8, 4));
    }

    public function test_regenerate_backfills_legacy_assets_without_variants(): void
    {
        // Simulate a legacy asset: original stored, no variants (broken-era upload)
        $img = UploadedFile::fake()->image('legacy.jpg', 1000, 700);
        $path = "sites/{$this->site->id}/assets/legacy-test.jpg";
        Storage::disk('assets')->put($path, file_get_contents($img->getRealPath()));
        $asset = Asset::factory()->create([
            'site_id' => $this->site->id,
            'storage_path' => $path,
            'mime_type' => 'image/jpeg',
            'variants' => [],
            'dimensions' => null,
        ]);

        $variants = app(AssetService::class)->regenerateVariants($asset);

        $this->assertArrayHasKey('webp_800', $variants);
        $this->assertTrue(Storage::disk('assets')->exists($variants['webp_800']));
        $fresh = $asset->fresh();
        $this->assertNotEmpty($fresh->variants);
        $this->assertSame(['width' => 1000, 'height' => 700], $fresh->dimensions);
    }

    public function test_backfill_command_processes_legacy_assets(): void
    {
        $img = UploadedFile::fake()->image('cmd.jpg', 900, 600);
        $path = "sites/{$this->site->id}/assets/cmd-test.jpg";
        Storage::disk('assets')->put($path, file_get_contents($img->getRealPath()));
        $asset = Asset::factory()->create([
            'site_id' => $this->site->id,
            'storage_path' => $path,
            'mime_type' => 'image/jpeg',
            'variants' => [],
        ]);

        $this->artisan('assets:generate-variants')->assertExitCode(0);

        $this->assertNotEmpty($asset->fresh()->variants);
    }

    public function test_publisher_resolves_and_copies_variant_files(): void
    {
        $img = UploadedFile::fake()->image('pub.jpg', 1000, 700);
        $path = "sites/{$this->site->id}/assets/pub-test.jpg";
        Storage::disk('assets')->put($path, file_get_contents($img->getRealPath()));
        $asset = Asset::factory()->create([
            'site_id' => $this->site->id,
            'storage_path' => $path,
            'mime_type' => 'image/jpeg',
            'checksum' => str_repeat('a', 64),
            'variants' => [],
        ]);
        app(AssetService::class)->regenerateVariants($asset);

        $target = storage_path('framework/testing/publish-' . uniqid());
        File::ensureDirectoryExists($target);
        AssetPublisher::reset();
        AssetPublisher::setDeployTarget($target);

        $html = '<img src="/api/v1/sites/' . $this->site->id . '/assets/' . $asset->id . '/serve/webp_800">'
            . '<img src="/api/v1/sites/' . $this->site->id . '/assets/' . $asset->id . '/serve">';
        $rewritten = AssetPublisher::rewriteHtml($html);
        AssetPublisher::reset();

        $hash = str_repeat('a', 64);
        // Variant URL rewritten cleanly (not mangled) and the file copied
        $this->assertStringContainsString("/assets/files/{$hash}_webp_800.webp", $rewritten);
        $this->assertStringContainsString("/assets/files/{$hash}.jpg", $rewritten);
        $this->assertFileExists("{$target}/assets/files/{$hash}_webp_800.webp");
        $this->assertFileExists("{$target}/assets/files/{$hash}.jpg");

        File::deleteDirectory($target);
    }
}
