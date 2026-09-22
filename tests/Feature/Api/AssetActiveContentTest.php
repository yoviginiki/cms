<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F06 (audit 2026-09-22) — an allowed text extension (.txt/.md) could carry
 * HTML; the sniffed text/html MIME was stored and served INLINE from the CMS
 * origin, so the .html extension ban was not enough. Active content is now
 * refused at upload, and the serve endpoint never sends an executable
 * Content-Type inline.
 */
class AssetActiveContentTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAsOwner()->post(
            "/api/v1/sites/{$this->site->id}/assets",
            ['file' => $file],
            array_merge($this->apiHeaders(), ['Accept' => 'application/json']),
        );
    }

    public function test_html_content_under_a_text_extension_is_rejected(): void
    {
        $html = "<!DOCTYPE html>\n<html><head><title>x</title></head><body><script>alert(document.cookie)</script></body></html>";
        foreach (['page.txt', 'notes.md'] as $name) {
            // Fake uploads report a MIME derived from the NAME; a real upload
            // is sniffed from content (finfo → text/html). Use the real class.
            $tmp = tempnam(sys_get_temp_dir(), 'up');
            file_put_contents($tmp, $html);
            $this->upload(new UploadedFile($tmp, $name, 'text/plain', null, true))->assertStatus(422);
            // …and the extension-derived MIME path (polyglot text/plain) is caught by the content sniff.
            $this->upload(UploadedFile::fake()->createWithContent($name, $html))->assertStatus(422);
        }
        $this->assertSame(0, Asset::where('site_id', $this->site->id)->count());
    }

    public function test_plain_text_still_uploads_and_is_served_as_an_attachment(): void
    {
        $res = $this->upload(UploadedFile::fake()->createWithContent('notes.txt', "just notes\nline two"));
        $res->assertStatus(201);
        $asset = Asset::findOrFail($res->json('data.id'));

        $serve = $this->actingAsOwner()->get("/api/v1/sites/{$this->site->id}/assets/{$asset->id}/serve");
        $serve->assertOk();
        $this->assertStringStartsWith('text/plain', $serve->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', $serve->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $serve->headers->get('X-Content-Type-Options'));
    }

    public function test_images_stay_inline(): void
    {
        $res = $this->upload(UploadedFile::fake()->image('photo.jpg', 40, 30));
        $res->assertStatus(201);
        $asset = Asset::findOrFail($res->json('data.id'));

        $serve = $this->actingAsOwner()->get("/api/v1/sites/{$this->site->id}/assets/{$asset->id}/serve");
        $serve->assertOk();
        $this->assertStringStartsWith('image/jpeg', $serve->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $serve->headers->get('Content-Disposition'));
    }

    public function test_legacy_rows_with_an_active_mime_are_never_served_executable(): void
    {
        Storage::disk('assets')->put("sites/{$this->site->id}/assets/legacy.txt", '<script>alert(1)</script>');
        foreach (['text/html', 'application/xhtml+xml', 'image/svg+xml; charset=utf-8', 'text/javascript'] as $mime) {
            $asset = Asset::create([
                'site_id' => $this->site->id, 'original_name' => 'legacy.txt',
                'storage_path' => "sites/{$this->site->id}/assets/legacy.txt",
                'mime_type' => $mime, 'file_size' => 25, 'checksum' => sha1($mime),
            ]);
            $serve = $this->actingAsOwner()->get("/api/v1/sites/{$this->site->id}/assets/{$asset->id}/serve");
            $serve->assertOk();
            $ct = strtolower((string) $serve->headers->get('Content-Type'));
            if ($mime === 'image/svg+xml; charset=utf-8') {
                // sanitized-at-upload SVGs stay inline, but with a CSP that blocks scripts
                $this->assertStringStartsWith('image/svg+xml', $ct);
                $this->assertStringContainsString("script-src 'none'", (string) $serve->headers->get('Content-Security-Policy'));
            } else {
                $this->assertStringNotContainsString('html', $ct);
                $this->assertStringNotContainsString('javascript', $ct);
                $this->assertStringStartsWith('attachment', (string) $serve->headers->get('Content-Disposition'));
            }
        }
    }
}
