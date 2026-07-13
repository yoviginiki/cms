<?php

namespace Tests\Feature\Publishing;

use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * assets:import-external — retrofits hotlinked Pexels/loremflickr images
 * into the media library, rewriting block data + featured images and
 * flagging touched published content stale.
 */
class ImportExternalImagesTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function jpeg(): string
    {
        $im = imagecreatetruecolor(600, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 60, 90));
        ob_start();
        imagejpeg($im, null, 80);
        imagedestroy($im);

        return ob_get_clean();
    }

    public function test_rewrites_hotlinked_images_and_flags_content_stale(): void
    {
        Http::fake(['images.pexels.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $page = Page::factory()->create(['site_id' => $this->site->id, 'status' => 'published', 'needs_republish' => false]);
        $block = Block::factory()->create([
            'blockable_id' => $page->id, 'blockable_type' => 'page', 'type' => 'image', 'order' => 0,
            'data' => ['url' => 'https://images.pexels.com/photos/123/x.jpeg?w=600', 'alt' => 'keep-me'],
        ]);
        $post = Post::factory()->published()->create([
            'site_id' => $this->site->id, 'category_id' => null,
            'featured_image' => 'https://images.pexels.com/photos/456/y.jpeg',
            'needs_republish' => false,
        ]);
        // disallowed host must remain untouched
        $other = Block::factory()->create([
            'blockable_id' => $page->id, 'blockable_type' => 'page', 'type' => 'image', 'order' => 1,
            'data' => ['url' => 'https://images.pexels.com/photos/123/x.jpeg?w=600', 'caption' => 'https://example.com/keep.jpg'],
        ]);

        $this->artisan('assets:import-external')->assertExitCode(0);

        $data = $block->fresh()->data;
        $this->assertStringContainsString("/api/v1/sites/{$this->site->id}/assets/", $data['url']);
        $this->assertSame('keep-me', $data['alt']);
        $this->assertStringContainsString('/serve', $post->fresh()->featured_image);
        $this->assertSame('https://example.com/keep.jpg', $other->fresh()->data['caption']);
        // same URL imported once (dedupe), both rewritten to the same asset
        $this->assertSame($data['url'], $other->fresh()->data['url']);
        // touched published content flagged for republish
        $this->assertTrue((bool) $page->fresh()->needs_republish);
        $this->assertTrue((bool) $post->fresh()->needs_republish);
    }

    public function test_dry_run_changes_nothing(): void
    {
        Http::fake();
        $post = Post::factory()->published()->create([
            'site_id' => $this->site->id, 'category_id' => null,
            'featured_image' => 'https://images.pexels.com/photos/1/a.jpeg',
            'needs_republish' => false,
        ]);

        $this->artisan('assets:import-external', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('https://images.pexels.com/photos/1/a.jpeg', $post->fresh()->featured_image);
        $this->assertFalse((bool) $post->fresh()->needs_republish);
        Http::assertNothingSent();
    }
}
