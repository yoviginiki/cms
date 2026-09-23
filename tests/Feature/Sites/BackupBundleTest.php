<?php

namespace Tests\Feature\Sites;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Asset;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Services\BackupBundleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F19 follow-up — the bundle carries asset BYTES; a restore into a clean
 * site recreates the files under new ids and rewrites every reference
 * (block data, serve URLs, featured images) to the new asset/site ids.
 */
class BackupBundleTest extends TestCase
{
    private Site $site;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dir = storage_path('framework/testing/bundle-' . uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function upload(string $name, $file): Asset
    {
        $res = $this->actingAsOwner()->post("/api/v1/sites/{$this->site->id}/assets", ['file' => $file], $this->apiHeaders())->assertStatus(201);

        return Asset::findOrFail($res->json('data.id'));
    }

    public function test_bundle_round_trip_restores_files_and_remaps_references(): void
    {
        $img = $this->upload('photo.jpg', UploadedFile::fake()->image('photo.jpg', 64, 48));
        $doc = $this->upload('notes.txt', UploadedFile::fake()->createWithContent('notes.txt', "plain notes\n"));
        $serve = "/api/v1/sites/{$this->site->id}/assets/{$img->id}/serve";

        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'home']);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $img->id, 'url' => $serve, 'alt' => 'x']],
        ]);
        $post = Post::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'p', 'featured_image' => $serve]);

        $zipPath = "{$this->dir}/b.zip";
        $out = app(BackupBundleService::class)->export($this->site->fresh(), $zipPath);
        $this->assertSame(2, $out['assets']);

        $target = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $report = app(BackupBundleService::class)->restore($zipPath, $target);
        $this->assertSame(2, $report['assets']);
        $this->assertSame(1, $report['pages']);

        $newImg = Asset::where('site_id', $target->id)->where('original_name', $img->original_name)->firstOrFail();
        $this->assertNotSame($img->id, $newImg->id);
        $this->assertTrue(Storage::disk('assets')->exists($newImg->storage_path));
        $this->assertSame(Storage::disk('assets')->get($img->storage_path), Storage::disk('assets')->get($newImg->storage_path));
        $this->assertSame(1, Asset::where('site_id', $target->id)->where('original_name', $doc->original_name)->count());

        $rPage = Page::where('site_id', $target->id)->where('slug', 'home')->firstOrFail();
        $data = app(BlockService::class)->getBlockTree($rPage)[0]['data'];
        $this->assertSame($newImg->id, $data['asset_id']);
        $this->assertSame("/api/v1/sites/{$target->id}/assets/{$newImg->id}/serve", $data['url']);
        $this->assertSame("/api/v1/sites/{$target->id}/assets/{$newImg->id}/serve", Post::where('site_id', $target->id)->firstOrFail()->featured_image);

        // the restored reference actually serves the file
        $this->actingAsOwner()->get($data['url'])->assertOk();
    }

    public function test_tampered_and_hostile_bundles_are_refused_and_leave_nothing_behind(): void
    {
        $img = $this->upload('photo.jpg', UploadedFile::fake()->image('photo.jpg', 20, 20));
        $zipPath = "{$this->dir}/b.zip";
        app(BackupBundleService::class)->export($this->site->fresh(), $zipPath);

        // tampered bytes → checksum mismatch, no asset rows, no files
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $zip->addFromString("assets/{$img->id}.jpg", 'TAMPERED');
        $zip->close();
        $target = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        try {
            app(BackupBundleService::class)->restore($zipPath, $target);
            $this->fail('expected checksum refusal');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Checksum', $e->getMessage());
        }
        $this->assertSame(0, Asset::where('site_id', $target->id)->count());
        $this->assertSame([], Storage::disk('assets')->allFiles("sites/{$target->id}"));

        // unexpected entries (traversal / executable) → refused before anything is written
        foreach (['../evil.php' => '<?php', 'assets/' . $img->id . '.php' => '<?php', 'extra.txt' => 'x'] as $name => $content) {
            $bad = "{$this->dir}/bad-" . md5($name) . '.zip';
            copy($zipPath, $bad);
            $z = new \ZipArchive();
            $z->open($bad);
            $z->addFromString($name, $content);
            $z->close();
            try {
                app(BackupBundleService::class)->restore($bad, $target);
                $this->fail("expected refusal for {$name}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Unexpected entry', $e->getMessage());
            }
        }
        $this->assertSame(0, Page::where('site_id', $target->id)->count());
    }

    public function test_download_endpoint_is_admin_only_and_returns_a_zip(): void
    {
        $editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($editor, 'sanctum')->get("/api/v1/sites/{$this->site->id}/backup/bundle", $this->apiHeaders())->assertForbidden();
        $r = $this->actingAsAdmin()->get("/api/v1/sites/{$this->site->id}/backup/bundle", $this->apiHeaders());
        $r->assertOk();
        $this->assertSame('application/zip', $r->headers->get('Content-Type'));
    }

    public function test_cli_export_and_restore(): void
    {
        $this->upload('photo.jpg', UploadedFile::fake()->image('photo.jpg', 20, 20));
        Page::factory()->create(['site_id' => $this->site->id, 'slug' => 'about']);
        $zipPath = "{$this->dir}/cli.zip";
        $this->artisan('cms:backup:export', ['site' => $this->site->slug, '--out' => $zipPath])->assertExitCode(0);
        $this->assertFileExists($zipPath);

        $this->setTenantScope($this->owner);
        $target = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->artisan('cms:backup:restore', ['bundle' => $zipPath, 'site' => $target->slug, '--no-variants' => true])->assertExitCode(0);
        $this->setTenantScope($this->owner);
        $this->assertSame(1, Page::where('site_id', $target->id)->count());
        $this->assertSame(1, Asset::where('site_id', $target->id)->count());
    }
}
