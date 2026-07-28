<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use Tests\TestCase;

/**
 * PLATFORM RULE: entrance animations are SCROLL-TRIGGERED by default.
 *
 * Below-the-fold entrances must not run at page load (they finish unseen and
 * the page reads as static). Every built page arms a paused state via an
 * <html> flag set in <head> — so no-JS visitors see everything statically —
 * and an IntersectionObserver resumes each element as it scrolls into view.
 * This test pins the mechanism so a pipeline refactor can't silently drop it.
 */
class ScrollEntranceAnimationTest extends TestCase
{
    public function test_built_pages_arm_scroll_triggered_entrances(): void
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);

        $section = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => [],
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => $section->id, 'type' => 'heading', 'order' => 0,
            'data' => ['text' => 'Animated heading', 'level' => 'h2', '__animation' => ['entrance' => 'slide-up', 'duration' => 600]],
        ]);

        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);

        // The animated element carries an inline entrance animation…
        $this->assertStringContainsString('animation-name:block-slide-up', $html);
        // …which is HELD until scrolled into view (flag set in <head>, JS-only)…
        $this->assertStringContainsString("setAttribute('data-anim-scroll'", $html);
        $this->assertStringContainsString('animation-play-state:paused', $html);
        // …and released per element by the IntersectionObserver runtime…
        $this->assertStringContainsString('IntersectionObserver', $html);
        $this->assertStringContainsString("animationPlayState='running'", $html);
        // …with a reduced-motion escape hatch.
        $this->assertStringContainsString('prefers-reduced-motion', $html);
    }
}
