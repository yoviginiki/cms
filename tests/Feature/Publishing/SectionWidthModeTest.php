<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * P5 Layout Logic — section content-width modes. The section's inner wrapper
 * (the max-width column) is driven by `width_mode`:
 *   contained (default/legacy) → max-width = max_width, centered
 *   wide                       → max-width = 1440px, centered
 *   full                       → full-bleed, no max-width, no centering
 * Additive: an unset width_mode must keep the legacy contained behavior.
 */
class SectionWidthModeTest extends TestCase
{
    private function renderSection(array $data): string
    {
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create([
            'site_id' => $site->id,
            'status' => 'published',
        ]);
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'section', 'level' => 'section', 'order' => 0,
            'data' => $data,
        ]);

        return app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
    }

    public function test_unset_width_mode_keeps_legacy_contained_behavior(): void
    {
        $html = $this->renderSection(['max_width' => '1200px']);
        $this->assertStringContainsString('max-width:1200px;margin:0 auto;', $html);
    }

    public function test_contained_honors_custom_max_width(): void
    {
        $html = $this->renderSection(['width_mode' => 'contained', 'max_width' => '960px']);
        $this->assertStringContainsString('max-width:960px;margin:0 auto;', $html);
    }

    public function test_wide_uses_the_wide_container(): void
    {
        $html = $this->renderSection(['width_mode' => 'wide', 'max_width' => '1200px']);
        $this->assertStringContainsString('max-width:1440px;margin:0 auto;', $html);
    }

    public function test_full_bleed_drops_max_width_and_centering(): void
    {
        $html = $this->renderSection(['width_mode' => 'full', 'max_width' => '1200px']);
        $this->assertStringContainsString('max-width:none;margin:0;', $html);
        $this->assertStringNotContainsString('max-width:1200px', $html);
    }

    public function test_malicious_max_width_is_sanitized(): void
    {
        // hostile max_width must never reach the style attribute verbatim
        $html = $this->renderSection(['width_mode' => 'contained', 'max_width' => '1px;} </style><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        // safeDim rejects the value → falls back to the 1200px default
        $this->assertStringContainsString('max-width:1200px;margin:0 auto;', $html);
    }
}
