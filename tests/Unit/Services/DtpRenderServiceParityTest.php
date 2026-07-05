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

    public function test_focal_point_accepts_both_scales(): void
    {
        // canonical 0-1 (editor scale)
        $out = $this->renderFrame([
            'frame_type' => 'image',
            'content' => ['src' => 'https://x.test/i.jpg', 'focalPoint' => ['x' => 0.3, 'y' => 0.7]],
        ]);
        $this->assertStringContainsString('object-position:30% 70%', $out['html']);

        // legacy 0-100 saves keep rendering correctly
        $out2 = $this->renderFrame([
            'frame_type' => 'image',
            'content' => ['src' => 'https://x.test/i.jpg', 'focalPoint' => ['x' => 25, 'y' => 80]],
        ]);
        $this->assertStringContainsString('object-position:25% 80%', $out2['html']);
    }

    public function test_image_content_mode_and_filters_publish(): void
    {
        $out = $this->renderFrame([
            'frame_type' => 'image',
            'content' => [
                'src' => 'https://x.test/i.jpg',
                'imageOffsetX' => 12, 'imageOffsetY' => -8, 'imageScale' => 1.4, 'imageRotation' => 15,
                'filters' => ['brightness' => 90, 'contrast' => 110, 'saturation' => 100, 'grayscale' => true],
            ],
        ]);
        $html = $out['html'];
        $this->assertStringContainsString('transform:translate(12px, -8px) scale(1.4) rotate(15deg)', $html);
        $this->assertStringContainsString('brightness(90%)', $html);
        $this->assertStringContainsString('contrast(110%)', $html);
        $this->assertStringContainsString('grayscale(1)', $html);
        $this->assertStringNotContainsString('saturate(', $html); // 100 = no-op
    }

    public function test_vertical_align_and_drop_caps_publish(): void
    {
        $out = $this->renderFrame([
            'frame_type' => 'text',
            'content' => ['html' => '<p>Alpha bravo</p>', 'verticalAlign' => 'bottom'],
            'metadata' => ['_typography' => [
                'fontSize' => 14, 'lineHeight' => 1.5,
                'dropCap' => ['enabled' => true, 'lines' => 3, 'font' => 'Playfair Display', 'color' => '#E63B2E'],
            ]],
        ]);
        $html = $out['html'];
        $this->assertStringContainsString('justify-content:flex-end', $html);
        $this->assertStringContainsString('::first-letter', $html);
        $this->assertStringContainsString('font-family:Playfair Display', $html);
        $this->assertStringContainsString('float:left', $html);
        // no wrapper when defaults
        $plain = $this->renderFrame(['frame_type' => 'text', 'content' => ['html' => '<p>x</p>']]);
        $this->assertStringNotContainsString('::first-letter', $plain['html']);
        $this->assertStringNotContainsString('justify-content', $plain['html']);
    }

    public function test_table_frames_publish_as_real_tables(): void
    {
        $out = $this->renderFrame([
            'frame_type' => 'text',
            'content' => [
                'tableHeaders' => ['Name', 'Qty'],
                'tableRows' => [['Washi <b>x</b>', '3'], ['Vermilion', '1']],
                'tableStripes' => true,
                'tableBorderColor' => '#333333',
            ],
            'metadata' => ['_magType' => 'table_frame'],
        ]);
        $html = $out['html'];
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<th style="border:1px solid #333333', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('background:#fafaf8', $html); // stripe row
        $this->assertStringContainsString('Washi &lt;b&gt;x&lt;/b&gt;', $html); // cells escaped
        // plain text frames are unaffected
        $plain = $this->renderFrame(['frame_type' => 'text', 'content' => ['html' => '<p>x</p>']]);
        $this->assertStringNotContainsString('<table', $plain['html']);
    }

    public function test_page_number_formats_publish(): void
    {
        $out = $this->renderFrame([
            'frame_type' => 'pageNumber',
            'content' => ['format' => 'roman-lower', 'prefix' => 'p. ', 'suffix' => ' —', 'startAt' => 3],
        ], ['page_index' => 1]); // page_index 1 + startAt 3 => 4 => "iv"
        $this->assertStringContainsString('p. iv —', $out['html']);

        $out2 = $this->renderFrame([
            'frame_type' => 'pageNumber',
            'content' => ['format' => 'alpha-upper'],
        ], ['page_index' => 27]); // 28 => AB
        $this->assertStringContainsString('AB', $out2['html']);
    }

    public function test_master_pages_composite_at_publish(): void
    {
        $svc = app(\App\Domain\Magazine\Services\DtpRenderService::class);
        $page = new MagazineDtpPage();
        $page->forceFill(['page_index' => 4, 'width' => 595, 'height' => 842]); // page 5
        $masterDef = [
            'id' => 'master-a',
            'elements' => [
                ['id' => 'e1', 'type' => 'running_header', 'x' => 36, 'y' => 12, 'width' => 300, 'height' => 20,
                 'zIndex' => 0, 'data' => ['customText' => 'STILLOPRESS FOLIO <x>'],
                 'typography' => ['fontSize' => 9, 'letterSpacing' => 0.2]],
                ['id' => 'e2', 'type' => 'page_number', 'x' => 550, 'y' => 810, 'width' => 40, 'height' => 20,
                 'zIndex' => 0, 'data' => ['format' => 'roman-lower', 'prefix' => '', 'suffix' => '', 'startAt' => 1]],
                ['id' => 'e3', 'type' => 'unknown_widget', 'data' => []],
                ['id' => 'e4', 'type' => 'text_frame', 'visible' => false, 'data' => ['content' => '<p>hidden</p>']],
            ],
        ];
        $frames = $svc->renderMasterFrames($masterDef, $page);
        $this->assertCount(2, $frames); // unknown + hidden skipped
        $all = implode('', array_column($frames, 'html'));
        $this->assertStringContainsString('STILLOPRESS FOLIO &lt;x&gt;', $all); // escaped
        $this->assertStringContainsString('>v<', $all); // page 5 => roman-lower 'v'
        $this->assertTrue($frames[0]['fromMaster']);
        $this->assertStringStartsWith('master-', $frames[0]['id']);
    }

    public function test_master_verso_recto_application(): void
    {
        $svc = app(\App\Domain\Magazine\Services\DtpRenderService::class);
        $masterDef = ['id' => 'm', '_appliesTo' => 'verso', 'elements' => [
            ['id' => 'e', 'type' => 'running_header', 'data' => ['customText' => 'FOLIO'], 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 20, 'zIndex' => 0],
        ]];
        $left = new MagazineDtpPage();
        $left->forceFill(['page_index' => 1, 'width' => 595, 'height' => 842, 'side' => 'left']);
        $right = new MagazineDtpPage();
        $right->forceFill(['page_index' => 2, 'width' => 595, 'height' => 842, 'side' => 'right']);
        $this->assertCount(1, $svc->renderMasterFrames($masterDef, $left));
        $this->assertCount(0, $svc->renderMasterFrames($masterDef, $right));
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
