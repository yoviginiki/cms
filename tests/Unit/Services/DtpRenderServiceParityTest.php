<?php

namespace Tests\Unit\Services;

use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Services\DtpRenderService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Session C Phase 3 — published-viewer parity pins.
 *
 * The flow engine bakes pagination into per-frame slices; the published
 * renderer must reproduce the editor's WITHIN-frame rendering exactly or
 * text wraps differently and clips. These tests pin every width-affecting
 * typography property plus the continued-fragment margin resets the engine
 * writes (data-flow-cont inline styles), and the spread-image overflow fix.
 *
 * No database is touched: models are unpersisted instances.
 */
class DtpRenderServiceParityTest extends TestCase
{
    private function renderFrame(array $frameAttrs, array $pageAttrs = []): array
    {
        $svc = app(DtpRenderService::class);
        $frame = new MagazineFrame();
        $frame->forceFill(array_merge([
            'x' => 10, 'y' => 10, 'width' => 200, 'height' => 100,
            'rotation' => 0, 'z_index' => 1, 'visible' => true, 'locked' => false,
            'name' => 'Test', 'frame_type' => 'text', 'content' => [], 'metadata' => [],
        ], $frameAttrs));
        $page = new MagazineDtpPage();
        $page->forceFill(array_merge(['page_index' => 0, 'width' => 595, 'height' => 842], $pageAttrs));

        $m = new ReflectionMethod($svc, 'renderFrame');
        $m->setAccessible(true);

        return $m->invoke($svc, $frame, $page);
    }

    public function test_typography_emits_every_width_affecting_property(): void
    {
        $out = $this->renderFrame([
            'frame_type' => 'text',
            'content' => ['html' => '<p>Hello world</p>', 'columnsInFrame' => 3, 'columnGap' => 14],
            'metadata' => ['_typography' => [
                'fontFamily' => 'Playfair Display',
                'fontSize' => 15.5,
                'fontWeight' => 600,
                'fontStyle' => 'italic',
                'lineHeight' => 1.6,
                'textAlign' => 'justify',
                'textColor' => '#1a1a1a',
                'letterSpacing' => 0.02,
                'textTransform' => 'uppercase',
                'hyphenation' => true,
            ]],
        ]);

        $style = $out['style'];
        $this->assertStringContainsString('font-family:Playfair Display', $style);
        $this->assertStringContainsString('font-size:15.5px', $style, 'fractional sizes must not be truncated');
        $this->assertStringContainsString('font-style:italic', $style);
        $this->assertStringContainsString('text-transform:uppercase', $style);
        $this->assertStringContainsString('hyphens:auto', $style);
        $this->assertStringContainsString('overflow-wrap:break-word', $style);
        $this->assertStringContainsString('column-count:3', $style);
        $this->assertStringContainsString('column-gap:14px', $style);
        $this->assertStringContainsString('text-align:justify', $style);
    }

    public function test_small_caps_maps_to_font_variant(): void
    {
        $out = $this->renderFrame([
            'content' => ['html' => '<p>x</p>'],
            'metadata' => ['_typography' => ['textTransform' => 'small-caps']],
        ]);
        $this->assertStringContainsString('font-variant:small-caps', $out['style']);
        $this->assertStringNotContainsString('text-transform:small-caps', $out['style']);
    }

    public function test_flow_continuation_margin_resets_survive_sanitization(): void
    {
        // the engine writes continued fragments with inline margin/indent
        // resets; the sanitizer's style allowlist must keep them
        $out = $this->renderFrame([
            'content' => ['html' => '<p style="margin-top: 0; text-indent: 0;" data-flow-cont="in">continued fragment</p>'],
        ]);
        $this->assertStringContainsString('margin-top', $out['html']);
        $this->assertStringContainsString('text-indent', $out['html']);
        $this->assertStringContainsString('continued fragment', $out['html']);
    }

    public function test_inline_markup_survives_in_slices(): void
    {
        $out = $this->renderFrame([
            'content' => ['html' => '<p>alpha <b>bold</b> <i>ital</i> <a href="https://x.test/a">link</a></p>'],
        ]);
        $this->assertStringContainsString('<b>bold</b>', $out['html']);
        $this->assertStringContainsString('<i>ital</i>', $out['html']);
        $this->assertStringContainsString('href="https://x.test/a"', $out['html']);
    }

    public function test_page_style_allows_spread_image_overflow(): void
    {
        $svc = app(DtpRenderService::class);
        $page = new MagazineDtpPage();
        $page->forceFill(['page_index' => 0, 'width' => 595, 'height' => 842]);
        $m = new ReflectionMethod($svc, 'buildPageStyle');
        $m->setAccessible(true);

        $this->assertStringContainsString('overflow:hidden', $m->invoke($svc, $page, false));
        $this->assertStringContainsString('overflow:visible', $m->invoke($svc, $page, true), 'spread-spanning images must not clip at the page edge');
    }

    public function test_fonts_url_collects_document_families(): void
    {
        $svc = app(DtpRenderService::class);
        $f1 = new MagazineFrame();
        $f1->forceFill(['metadata' => ['_typography' => ['fontFamily' => 'Playfair Display']]]);
        $f2 = new MagazineFrame();
        $f2->forceFill(['metadata' => ['_typography' => ['fontFamily' => 'Merriweather']]]);
        $m = new ReflectionMethod($svc, 'buildFontsUrl');
        $m->setAccessible(true);
        $url = $m->invoke($svc, collect([$f1, $f2]));

        $this->assertStringContainsString('fonts.googleapis.com/css2', $url);
        $this->assertStringContainsString('Playfair+Display', $url);
        $this->assertStringContainsString('Merriweather', $url);
        $this->assertStringContainsString('Inter', $url);
        $this->assertStringContainsString('display=swap', $url);
    }
}
