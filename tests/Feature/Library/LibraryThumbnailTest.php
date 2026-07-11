<?php

namespace Tests\Feature\Library;

use App\Domain\Library\Services\LibraryThumbnailService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Library preview thumbnails (Builder P1 Slice E). The render path materialises
 * a detached block tree in a transaction and rolls it back, so nothing leaks;
 * the serve route streams the cached PNG from the assets disk.
 */
class LibraryThumbnailTest extends TestCase
{
    private function tree(): array
    {
        return [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
            'children' => [[
                'type' => 'heading', 'level' => 'module', 'order' => 0,
                'data' => ['text' => 'THUMBTEST-MARKER', 'level' => 'h2'], 'children' => [],
            ]],
        ]];
    }

    public function test_render_html_wraps_tokens_and_rolls_back(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $html = app(LibraryThumbnailService::class)->renderHtml($this->tree(), $site);

        // the item's content rendered, wrapped in the site's design tokens
        $this->assertStringContainsString('THUMBTEST-MARKER', $html);
        $this->assertStringContainsString(':root', $html);
        $this->assertStringContainsString('<!doctype html>', $html);

        // the throwaway page + its blocks were rolled back — nothing persists
        $this->assertSame(0, Page::where('title', '__thumb')->count());
        $this->assertSame(0, Block::where('blockable_id', $site->id)->count());
    }

    public function test_serve_streams_png_when_present_and_404s_otherwise(): void
    {
        Storage::fake('assets');
        $id = '11111111-1111-4111-8111-111111111111';

        // missing → 404
        $this->get("/library-thumbnails/{$id}")->assertNotFound();

        // present → streamed as image/png
        Storage::disk('assets')->put("library-thumbs/{$id}.png", 'PNGBYTES');
        $resp = $this->get("/library-thumbnails/{$id}");
        $resp->assertOk();
        $this->assertSame('image/png', $resp->headers->get('Content-Type'));
    }

    public function test_bad_id_is_rejected_by_the_route_constraint(): void
    {
        $this->get('/library-thumbnails/not-a-uuid')->assertNotFound();
    }
}
