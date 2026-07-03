<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Session 2 round-trip tests: set inspector controls to non-default values,
 * render through the REAL publish pipeline, assert the output preserves them.
 * Pins the S1/S2 remediation (tokens, opacity, alignItems, border fallback).
 */
class InspectorRoundTripTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function renderPageWith(array $blockTree): string
    {
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, $blockTree);

        return app(BuildPageService::class)->build($page->fresh(), null, $this->site);
    }

    public function test_theme_token_colors_survive_to_published_css(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'text', 'order' => 0,
            'data' => ['content' => 'Token colored'],
            'style' => ['typography' => ['textColor' => 'var(--color-primary)']],
        ]]);

        $this->assertStringContainsString('color:var(--color-primary)', $html);
    }

    public function test_token_pattern_rejects_injection_attempts(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'text', 'order' => 0,
            'data' => ['content' => 'Evil'],
            'style' => ['typography' => ['textColor' => 'var(--x);background:url(javascript:1)']],
        ]]);

        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('url(', $html);
    }

    public function test_block_opacity_is_emitted(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'text', 'order' => 0,
            'data' => ['content' => 'Half visible'],
            'style' => ['visual' => ['opacity' => 0.5]],
        ]]);

        $this->assertStringContainsString('opacity:0.5', $html);
    }

    public function test_border_width_without_color_falls_back_to_currentcolor(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'text', 'order' => 0,
            'data' => ['content' => 'Bordered'],
            'style' => ['visual' => ['borderWidth' => '2px', 'borderStyle' => 'dashed']],
        ]]);

        $this->assertStringContainsString('border:2px dashed currentColor', $html);
    }

    public function test_align_items_is_emitted_for_flex_blocks(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'group', 'order' => 0,
            'data' => [],
            'style' => ['layout' => ['display' => 'flex', 'alignItems' => 'center']],
        ]]);

        $this->assertStringContainsString('align-items:center', $html);
    }

    public function test_image_object_fit_and_position_reach_published_css(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'image', 'order' => 0,
            'data' => [
                'url' => 'https://example.com/pic.jpg', 'alt' => 'x',
                'objectFit' => 'contain', 'objectPosition' => 'left top',
                'width' => '640', 'height' => '480',
            ],
        ]]);

        $this->assertStringContainsString('object-fit:contain', $html);
        $this->assertStringContainsString('object-position:left top', $html);
        $this->assertStringContainsString('width="640"', $html);
        $this->assertStringContainsString('height="480"', $html);
    }

    public function test_video_controls_preload_playsinline_are_editor_driven(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'video', 'order' => 0,
            'data' => [
                'url' => 'https://example.com/clip.mp4',
                'controls' => false, 'playsinline' => true, 'preload' => 'none',
                'muted' => true, 'loop' => true,
            ],
        ]]);

        $this->assertStringNotContainsString('<video controls', $html);
        $this->assertStringContainsString('playsinline', $html);
        $this->assertStringContainsString('preload="none"', $html);
    }

    public function test_audio_loop_volume_preload_and_no_autoplay_invariant(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'audio', 'order' => 0,
            'data' => ['url' => 'https://example.com/track.mp3', 'loop' => true, 'volume' => 0.4, 'preload' => 'none'],
        ]]);

        $this->assertMatchesRegularExpression('/<audio[^>]*controls/', $html);
        $this->assertMatchesRegularExpression('/<audio[^>]*loop/', $html);
        $this->assertStringContainsString('data-volume="0.4"', $html);
        $this->assertStringContainsString('preload="none"', $html);
        $this->assertDoesNotMatchRegularExpression('/<audio[^>]*autoplay/', $html);
    }

    public function test_token_dimension_survives_safe_dim(): void
    {
        $html = $this->renderPageWith([[
            'type' => 'text', 'order' => 0,
            'data' => ['content' => 'Token padded'],
            'style' => ['spacing' => ['paddingTop' => 'var(--spacing-block-gap, 24px)']],
        ]]);

        $this->assertStringContainsString('padding-top:var(--spacing-block-gap, 24px)', $html);
    }
}
