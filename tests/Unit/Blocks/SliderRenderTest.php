<?php

namespace Tests\Unit\Blocks;

use App\Models\Block;
use App\Support\Blocks\SliderRender;
use Tests\TestCase;

/**
 * Phase 5: per-breakpoint layer layout reaches the published CSS with the
 * same breakpoints as BlockStyle's responsive emitters (≤1023 / ≤767).
 */
class SliderRenderTest extends TestCase
{
    private function layer(array $data): Block
    {
        $block = new Block(['type' => 'text', 'data' => $data, 'order' => 0]);
        $block->id = '0198aaaa-bbbb-cccc-dddd-eeeeffff0001';

        return $block;
    }

    public function test_wrap_layer_emits_final_state_positioning(): void
    {
        $html = SliderRender::wrapLayer($this->layer([
            'layout' => ['x' => '8%', 'y' => '32%', 'widthPct' => 40, 'rotation' => -6, 'zIndex' => 3],
            'animation' => ['in' => ['preset' => 'fadeUp']],
        ]), '<p>inner</p>');

        $this->assertStringContainsString('left:8%', $html);
        $this->assertStringContainsString('top:32%', $html);
        $this->assertStringContainsString('width:40%', $html);
        $this->assertStringContainsString('rotate(-6deg)', $html);
        $this->assertStringContainsString('data-animated', $html);
        $this->assertStringContainsString('data-layer-id=', $html);
    }

    public function test_responsive_layout_overrides_emit_scoped_media_queries(): void
    {
        $html = SliderRender::wrapLayer($this->layer([
            'layout' => ['x' => '8%', 'y' => '32%'],
            'responsiveLayout' => [
                'tablet' => ['x' => '5%', 'widthPct' => 60],
                'mobile' => ['y' => '10%', 'hidden' => true],
            ],
        ]), 'x');

        $this->assertStringContainsString('@media (max-width:1023px)', $html);
        $this->assertStringContainsString('left:5% !important', $html);
        $this->assertStringContainsString('width:60% !important', $html);
        $this->assertStringContainsString('@media (max-width:767px)', $html);
        $this->assertStringContainsString('top:10% !important', $html);
        $this->assertStringContainsString('display:none !important', $html);
        // scope class present on both the style rules and the wrapper
        $this->assertMatchesRegularExpression('/class="sp-layer spl-[0-9a-f]{8}"/', $html);
    }

    public function test_unsafe_responsive_values_are_dropped(): void
    {
        $html = SliderRender::wrapLayer($this->layer([
            'layout' => ['x' => '8%'],
            'responsiveLayout' => ['mobile' => ['x' => 'expression(alert(1))']],
        ]), 'x');

        $this->assertStringNotContainsString('expression', $html);
        $this->assertStringContainsString('left:0% !important', $html); // safe fallback
    }
}
