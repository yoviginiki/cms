<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Canvas editor — Phase 1 (data & rendering). A canvas page is a vertical
 * stack of Section canvases with absolutely-positioned block children; the
 * published output is freeform on desktop and auto-stacks below the design
 * width in reading (y, then x) source order.
 */
class CanvasRenderTest extends TestCase
{
    private function canvasPage(): array
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id,
            'editor_mode' => 'canvas',
            'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);

        // Section 1 (contained, fixed height) with two elements.
        $s1 = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 600, 'bleed' => false, 'background' => '#f5f5f5']],
        ]);
        // deliberately create the LOWER element first to prove source-order sorting
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s1->id, 'type' => 'text', 'order' => 0,
            'data' => ['content' => 'SECOND-BY-POSITION'],
            'style' => ['layout' => ['x' => 100, 'y' => 400, 'width' => 500, 'height' => 120, 'zIndex' => 2]],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s1->id, 'type' => 'heading', 'order' => 1,
            'data' => ['text' => 'FIRST-BY-POSITION', 'level' => 'h1'],
            'style' => ['layout' => ['x' => 80, 'y' => 40, 'width' => 600, 'height' => 90, 'rotation' => -3, 'zIndex' => 1]],
        ]);

        // Section 2 (full-bleed, auto height).
        $s2 = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 1,
            'data' => ['canvas' => ['height' => 'auto', 'bleed' => true, 'background' => '#1e293b']],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s2->id, 'type' => 'text', 'order' => 0,
            'data' => ['content' => 'BLEED-ELEMENT'],
            'style' => ['layout' => ['x' => 50, 'y' => 60, 'width' => 300, 'height' => 200]],
        ]);

        return [$site->fresh(), $page->fresh()];
    }

    public function test_canvas_page_renders_freeform_desktop_and_mobile_stack(): void
    {
        [$site, $page] = $this->canvasPage();
        $html = app(BuildPageService::class)->build($page, $site->theme, $site);

        // Desktop: positioning context + absolutely-positioned elements
        $this->assertStringContainsString('class="cv-page"', $html);
        $this->assertStringContainsString('class="cv-section"', $html);
        $this->assertMatchesRegularExpression('/class="cv-el" style="left:80px;top:40px;width:600px;height:90px;transform:rotate\(-3deg\);/', $html);
        $this->assertStringContainsString('height:600px', $html); // fixed section height

        // Full-bleed section wraps a content column
        $this->assertStringContainsString('class="cv-bleed"', $html);

        // Mobile auto-stack rule keyed to the design width
        $this->assertStringContainsString('@media(max-width:1200px)', $html);
        $this->assertStringContainsString('position:static!important', $html);

        // Reading order (within the canvas body, not the <head> meta): the
        // higher element (y=40) must appear in the markup BEFORE the lower one
        // (y=400), regardless of block `order`.
        $body = substr($html, (int) strpos($html, 'class="cv-page"'));
        $posFirst = strpos($body, 'FIRST-BY-POSITION');
        $posSecond = strpos($body, 'SECOND-BY-POSITION');
        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertLessThan($posSecond, $posFirst, 'canvas children must be emitted in y,x source order for SEO/a11y + mobile stack');
    }

    public function test_auto_height_section_fits_lowest_child(): void
    {
        [$site, $page] = $this->canvasPage();
        $html = app(BuildPageService::class)->build($page, $site->theme, $site);
        // bleed section child: y=60 + h=200 = 260
        $this->assertStringContainsString('height:260px', $html);
    }

    public function test_block_editor_pages_are_unaffected(): void
    {
        // Regression guard: a normal block-editor page must NOT get canvas markup.
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(1);
        $page = Page::where('site_id', $site->id)->firstOrFail(); // editor_mode defaults to 'block'

        $html = app(BuildPageService::class)->build($page, $site->theme, $site);
        $this->assertStringNotContainsString('class="cv-page"', $html);
        $this->assertStringNotContainsString('class="cv-el"', $html);
    }
}
