<?php

namespace Tests\Feature\Magazine;

use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Domain\Magazine\Services\MagazineToCanvasConverter;
use App\Models\Page;
use Tests\TestCase;

class MagazineToCanvasConverterTest extends TestCase
{
    private function magElement(Page $page, array $attrs): void
    {
        MagElement::create(array_merge([
            'page_id' => $page->id,
            'page_number' => 1,
            'x' => 0, 'y' => 0, 'width' => 100, 'height' => 50,
            'rotation' => 0, 'z_index' => 0, 'visible' => true, 'on_master' => false,
            'data' => [],
        ], $attrs));
    }

    public function test_converts_magazine_pages_and_elements_into_a_canvas_block_tree(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'editor_mode' => 'magazine']);

        MagPage::create([
            'page_id' => $page->id, 'page_number' => 1,
            'page_size' => ['width' => 595, 'height' => 842], 'background_color' => '#f5f5f5',
        ]);

        // z-order: text below (z=1), headline above (z=2) — expect z-index order preserved
        $this->magElement($page, ['type' => 'text_frame', 'z_index' => 1, 'x' => 40, 'y' => 300, 'width' => 300, 'height' => 120, 'data' => ['content' => '<p>Body</p>']]);
        $this->magElement($page, ['type' => 'headline_frame', 'z_index' => 2, 'x' => 40, 'y' => 40, 'width' => 500, 'height' => 80, 'rotation' => -2, 'data' => ['content' => '<b>Title</b>']]);
        $this->magElement($page, ['type' => 'image_frame', 'z_index' => 3, 'x' => 10, 'y' => 500, 'width' => 200, 'height' => 200, 'data' => ['src' => '/img/a.jpg', 'alt' => 'A']]);
        // these must be dropped:
        $this->magElement($page, ['type' => 'page_number', 'z_index' => 4]);          // no web equivalent
        $this->magElement($page, ['type' => 'text_frame', 'z_index' => 5, 'visible' => false]); // hidden
        $this->magElement($page, ['type' => 'text_frame', 'z_index' => 6, 'on_master' => true]); // master

        $tree = app(MagazineToCanvasConverter::class)->convert($page->fresh());

        $this->assertCount(1, $tree);
        $section = $tree[0];
        $this->assertSame('section', $section['type']);
        $this->assertSame(['height' => 842, 'bleed' => false, 'background' => '#f5f5f5'], $section['data']['canvas']);

        // 3 mappable elements survived, in z-index order
        $this->assertCount(3, $section['children']);
        $this->assertSame(['text', 'heading', 'image'], array_column($section['children'], 'type'));

        // heading mapped: tags stripped, level set, geometry carried
        $heading = $section['children'][1];
        $this->assertSame('Title', $heading['data']['text']);
        $this->assertSame('h2', $heading['data']['level']);
        $this->assertSame(
            ['position' => 'absolute', 'x' => 40, 'y' => 40, 'width' => '500px', 'height' => '80px', 'rotation' => -2.0, 'zIndex' => 2],
            $heading['style']['layout']
        );

        // image mapped
        $this->assertSame('/img/a.jpg', $section['children'][2]['data']['src']);

        // design width = widest magazine page
        $this->assertSame(595, app(MagazineToCanvasConverter::class)->designWidth($page->fresh()));
    }

    public function test_duplicate_as_canvas_endpoint_creates_a_draft_canvas_copy(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'editor_mode' => 'magazine', 'slug' => 'mag-orig']);
        MagPage::create(['page_id' => $page->id, 'page_number' => 1, 'page_size' => ['width' => 595, 'height' => 842]]);
        $this->magElement($page, ['type' => 'headline_frame', 'z_index' => 1, 'data' => ['content' => 'Hi']]);

        $res = $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/pages/{$page->id}/duplicate-as-canvas", [], $this->apiHeaders());
        $res->assertStatus(201);
        $newId = $res->json('data.id');

        $new = Page::findOrFail($newId);
        $this->assertSame('canvas', $new->editor_mode);
        $this->assertSame('draft', $new->status);
        $this->assertSame(595, $new->seo_meta['canvas']['width']);
        // original is untouched
        $this->assertSame('magazine', $page->fresh()->editor_mode);
        // converted section block exists on the copy
        $this->assertGreaterThan(0, \App\Models\Block::where('blockable_type', $new->getMorphClass())
            ->where('blockable_id', $newId)->where('type', 'section')->count());
    }
}
