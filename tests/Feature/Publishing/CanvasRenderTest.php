<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
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
        $this->assertMatchesRegularExpression('/class="cv-el" id="cve-[^"]*" style="left:80px;top:40px;width:600px;height:90px;transform:rotate\(-3deg\);/', $html);
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

    public function test_single_page_type_renders_only_the_first_section(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'single', 'width' => 1200]],
        ]);
        foreach ([0, 1] as $i) {
            $s = Block::create([
                'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
                'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => $i,
                'data' => ['canvas' => ['height' => 300, 'bleed' => false]],
            ]);
            Block::create([
                'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
                'parent_block_id' => $s->id, 'type' => 'text', 'order' => 0,
                'data' => ['content' => "SECTION-{$i}-MARKER"],
                'style' => ['layout' => ['x' => 10, 'y' => 10, 'width' => 200, 'height' => 50]],
            ]);
        }
        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
        $body = substr($html, (int) strpos($html, 'class="cv-page"'));
        $this->assertStringContainsString('SECTION-0-MARKER', $body);
        $this->assertStringNotContainsString('SECTION-1-MARKER', $body); // single: one canvas only
    }

    public function test_canvas_background_is_sanitized(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            // hostile background — must never reach the style attribute verbatim
            'data' => ['canvas' => ['height' => 200, 'bleed' => false, 'background' => 'red;} </style><script>alert(1)</script>']],
        ]);
        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('</style><script>', $html);
    }

    public function test_extreme_element_coordinates_are_clamped_to_safe_numbers(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);
        $s = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 400, 'bleed' => false]],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s->id, 'type' => 'text', 'order' => 0,
            'data' => ['content' => 'x'],
            // injection attempt via layout numbers + out-of-range rotation/zIndex
            'style' => ['layout' => ['x' => '10;background:url(evil)', 'y' => 5, 'width' => '200px', 'height' => 50, 'rotation' => 999999, 'zIndex' => 999999999]],
        ]);
        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
        $this->assertStringNotContainsString('background:url(evil)', $html);
        $this->assertStringContainsString('left:10px', $html);         // numeric part kept, injection stripped
        $this->assertMatchesRegularExpression('/z-index:\d{1,4};/', $html); // clamped ≤ 9999
        $this->assertDoesNotMatchRegularExpression('/rotate\(999999deg\)/', $html); // clamped ≤ 360
    }

    public function test_canvas_mode_works_for_posts_too(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $post = Post::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);
        $s = Block::create([
            'blockable_type' => $post->getMorphClass(), 'blockable_id' => $post->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 400, 'bleed' => false]],
        ]);
        Block::create([
            'blockable_type' => $post->getMorphClass(), 'blockable_id' => $post->id,
            'parent_block_id' => $s->id, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'POST-CANVAS', 'level' => 'h1'],
            'style' => ['layout' => ['x' => 60, 'y' => 30, 'width' => 400, 'height' => 80]],
        ]);
        $html = app(BuildPageService::class)->build($post->fresh(), $site->theme, $site);
        $this->assertStringContainsString('class="cv-page"', $html);
        $this->assertMatchesRegularExpression('/class="cv-el" id="cve-[^"]*" style="left:60px;top:30px;/', $html);
    }

    public function test_mobile_override_emits_a_phone_media_query(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200, 'mobile_width' => 390]],
        ]);
        $s = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 600, 'bleed' => false]],
        ]);
        $child = Block::create([
            'id' => '77777777-7777-4777-8777-777777777777',
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s->id, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'Hi', 'level' => 'h1'],
            // desktop base + a phone override (x/y/width; height inherits base)
            'style' => ['layout' => ['x' => 700, 'y' => 40, 'width' => '500px', 'height' => '90px', 'bp' => ['mobile' => ['x' => 12, 'y' => 24, 'width' => 340]]]],
        ]);
        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);

        // the element carries a stable id and the section is flagged mobile-custom
        $this->assertStringContainsString('id="cve-77777777-7777-4777-8777-777777777777"', $html);
        $this->assertMatchesRegularExpression('/class="cv-section cv-mob cvs-[0-9a-f-]+"/', $html);
        // a ≤767 media query carries the per-element override (width from override, height inherited = 90)
        $this->assertStringContainsString('@media(max-width:767px)', $html);
        $this->assertMatchesRegularExpression('/#cve-77777777[0-9a-f-]*\{left:12px!important;top:24px!important;right:auto!important;margin-left:0!important;width:340px!important;height:90px!important/', $html);
        // desktop inline still has the base position
        $this->assertStringContainsString('left:700px;top:40px;width:500px', $html);
    }

    public function test_fluid_section_positions_elements_by_pin_anchor(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id, 'editor_mode' => 'canvas', 'status' => 'published',
            'seo_meta' => ['canvas' => ['page_type' => 'website', 'width' => 1200]],
        ]);
        $s = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => ['canvas' => ['height' => 300, 'bleed' => false, 'fluid' => true]],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s->id, 'type' => 'text', 'order' => 0,
            'data' => ['content' => 'L'],
            'style' => ['layout' => ['x' => 50, 'y' => 20, 'width' => '200px', 'height' => '60px', 'pinX' => 'left']],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $s->id, 'type' => 'text', 'order' => 1,
            'data' => ['content' => 'R'],
            'style' => ['layout' => ['x' => 800, 'y' => 20, 'width' => '300px', 'height' => '60px', 'pinX' => 'right']],
        ]);
        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);

        // fluid container + it is excluded from the auto-stack media query
        $this->assertMatchesRegularExpression('/class="cv-section cv-fluid"/', $html);
        $this->assertStringContainsString('.cv-fluid{width:100%!important;max-width:var(--cv-w)!important', $html);
        $this->assertStringContainsString('.cv-section:not(.cv-fluid) .cv-el{position:static!important', $html);
        // left pin holds the left edge; right pin holds the right edge (rInset = 1200-(800+300)=100)
        $this->assertStringContainsString('style="left:50px;width:200px;top:20px', $html);
        $this->assertStringContainsString('style="right:100px;width:300px;top:20px', $html);
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
