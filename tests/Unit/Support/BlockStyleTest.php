<?php

namespace Tests\Unit\Support;

use App\Support\Blocks\BlockStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockStyleTest extends TestCase
{
    // ── safeDim ──

    #[DataProvider('validDimensionProvider')]
    public function test_safe_dim_accepts_valid(string $input, string $expected): void
    {
        $this->assertSame($expected, BlockStyle::safeDim($input));
    }

    public static function validDimensionProvider(): array
    {
        return [
            ['16px', '16px'],
            ['2rem', '2rem'],
            ['100%', '100%'],
            ['0', '0'],
            ['1.5em', '1.5em'],
            ['50vh', '50vh'],
            ['auto', 'auto'],  // note: 'auto' should match if regex allows it
        ];
    }

    #[DataProvider('invalidDimensionProvider')]
    public function test_safe_dim_rejects_invalid(string $input): void
    {
        $this->assertSame('', BlockStyle::safeDim($input));
    }

    public static function invalidDimensionProvider(): array
    {
        return [
            ['javascript:alert(1)'],
            ['expression(alert(1))'],
            ['16'],           // no unit
            ['<script>'],
            ['url(evil)'],
            ['; color: red'],
        ];
    }

    // ── safeColor ──

    #[DataProvider('validColorProvider')]
    public function test_safe_color_accepts_valid(string $input): void
    {
        $this->assertNotEmpty(BlockStyle::safeColor($input));
    }

    public static function validColorProvider(): array
    {
        return [
            ['#fff'],
            ['#1e40af'],
            ['#1e40af80'],
            ['rgb(255, 0, 0)'],
            ['rgba(0,0,0,0.5)'],
            ['red'],
            ['transparent'],
        ];
    }

    #[DataProvider('invalidColorProvider')]
    public function test_safe_color_rejects_invalid(string $input): void
    {
        $this->assertSame('', BlockStyle::safeColor($input));
    }

    public static function invalidColorProvider(): array
    {
        return [
            ['<script>alert(1)</script>'],
            ['expression(alert(1))'],
            ['url(javascript:alert(1))'],
            ['rgb(0,0,0); background: url(evil)'],
        ];
    }

    // ── safeShadow ──

    public function test_safe_shadow_allows_presets(): void
    {
        $this->assertNotEmpty(BlockStyle::safeShadow('sm'));
        $this->assertNotEmpty(BlockStyle::safeShadow('md'));
        $this->assertNotEmpty(BlockStyle::safeShadow('lg'));
    }

    public function test_safe_shadow_rejects_unknown(): void
    {
        $this->assertSame('', BlockStyle::safeShadow('none'));
        $this->assertSame('', BlockStyle::safeShadow('xl'));
        $this->assertSame('', BlockStyle::safeShadow('0 0 10px red; background: url(evil)'));
    }

    // ── safeAnimationName ──

    public function test_safe_animation_allows_known(): void
    {
        $this->assertSame('block-fade', BlockStyle::safeAnimationName('fade'));
        $this->assertSame('block-slide-up', BlockStyle::safeAnimationName('slide-up'));
        $this->assertSame('block-slide-down', BlockStyle::safeAnimationName('slide-down'));
        $this->assertSame('block-zoom', BlockStyle::safeAnimationName('zoom'));
        $this->assertSame('block-scale-in', BlockStyle::safeAnimationName('scale-in'));
    }

    public function test_safe_animation_rejects_unknown(): void
    {
        $this->assertSame('', BlockStyle::safeAnimationName('none'));
        $this->assertSame('', BlockStyle::safeAnimationName('bounce'));
        $this->assertSame('', BlockStyle::safeAnimationName('<script>'));
    }

    // ── safeClass ──

    public function test_safe_class_sanitizes(): void
    {
        $this->assertSame('my-class foo', BlockStyle::safeClass('my-class foo'));
        $this->assertSame('abcscript', BlockStyle::safeClass('abc<script>'));
        $this->assertSame('', BlockStyle::safeClass(''));
    }

    // ── buildStyle ──

    public function test_build_style_with_spacing(): void
    {
        $style = BlockStyle::buildStyle([
            'spacing' => ['paddingTop' => '16px', 'marginBottom' => '2rem'],
        ]);
        $this->assertStringContainsString('padding-top:16px', $style);
        $this->assertStringContainsString('margin-bottom:2rem', $style);
    }

    public function test_build_style_rejects_unsafe_spacing(): void
    {
        $style = BlockStyle::buildStyle([
            'spacing' => ['paddingTop' => 'expression(alert(1))'],
        ]);
        $this->assertStringNotContainsString('expression', $style);
    }

    public function test_build_style_with_border(): void
    {
        $style = BlockStyle::buildStyle([
            'visual' => ['borderWidth' => '2px', 'borderColor' => '#333', 'borderStyle' => 'dashed'],
        ]);
        $this->assertStringContainsString('border:2px dashed #333', $style);
    }

    public function test_build_style_with_animation(): void
    {
        $style = BlockStyle::buildStyle([], ['entrance' => 'fade', 'duration' => 600, 'delay' => 100]);
        $this->assertStringContainsString('animation-name:block-fade', $style);
        $this->assertStringContainsString('animation-duration:600ms', $style);
        $this->assertStringContainsString('animation-delay:100ms', $style);
        $this->assertStringContainsString('animation-timing-function:ease-out', $style);
    }

    public function test_build_style_with_custom_easing(): void
    {
        $style = BlockStyle::buildStyle([], ['entrance' => 'slide-up', 'easing' => 'ease-in-out']);
        $this->assertStringContainsString('animation-timing-function:ease-in-out', $style);
    }

    public function test_build_style_rejects_unsafe_easing(): void
    {
        $style = BlockStyle::buildStyle([], ['entrance' => 'fade', 'easing' => 'expression(alert(1))']);
        $this->assertStringContainsString('animation-timing-function:ease-out', $style);
        $this->assertStringNotContainsString('expression', $style);
    }

    public function test_build_style_with_new_animations(): void
    {
        $style = BlockStyle::buildStyle([], ['entrance' => 'slide-down']);
        $this->assertStringContainsString('animation-name:block-slide-down', $style);

        $style = BlockStyle::buildStyle([], ['entrance' => 'scale-in']);
        $this->assertStringContainsString('animation-name:block-scale-in', $style);
    }

    public function test_build_style_clamps_duration(): void
    {
        $style = BlockStyle::buildStyle([], ['entrance' => 'fade', 'duration' => 99999]);
        $this->assertStringContainsString('animation-duration:3000ms', $style);
    }

    public function test_build_style_empty_returns_empty(): void
    {
        $this->assertSame('', BlockStyle::buildStyle());
    }

    // ── buildClasses ──

    public function test_build_classes_with_custom_and_extra(): void
    {
        $cls = BlockStyle::buildClasses(['customClass' => 'my-hero'], 'hero-section');
        $this->assertStringContainsString('my-hero', $cls);
        $this->assertStringContainsString('hero-section', $cls);
    }

    // ── buildHideOnCss ──

    public function test_hide_on_generates_media_queries(): void
    {
        $result = BlockStyle::buildHideOnCss(['hideOn' => ['mobile', 'tablet']], 'hero-1');
        $this->assertNotEmpty($result['scopeClass']);
        $this->assertStringContainsString('@media(max-width:768px)', $result['css']);
        $this->assertStringContainsString('display:none!important', $result['css']);
    }

    public function test_hide_on_empty_returns_empty(): void
    {
        $result = BlockStyle::buildHideOnCss([], '');
        $this->assertSame('', $result['scopeClass']);
        $this->assertSame('', $result['css']);
    }

    // ── Visual Controls: Background ──

    public function test_build_style_with_background_color(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['backgroundColor' => '#ff0000']]);
        $this->assertStringContainsString('background-color:#ff0000', $style);
    }

    public function test_build_style_with_background_gradient(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['backgroundGradient' => 'linear-gradient(135deg, #667eea, #764ba2)']]);
        $this->assertStringContainsString('background:linear-gradient', $style);
    }

    public function test_build_style_with_background_image(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['backgroundImage' => 'https://example.com/img.jpg']]);
        $this->assertStringContainsString('background-image:url(', $style);
        $this->assertStringContainsString('background-size:cover', $style);
    }

    // ── Visual Controls: Per-corner Border Radius ──

    public function test_build_style_with_per_corner_radius(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['borderRadius' => [
            'topLeft' => '10px', 'topRight' => '20px', 'bottomRight' => '0', 'bottomLeft' => '5px',
        ]]]);
        $this->assertStringContainsString('border-radius:10px 20px 0 5px', $style);
        $this->assertStringContainsString('overflow:hidden', $style);
    }

    public function test_build_style_with_uniform_radius(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['borderRadius' => '12px']]);
        $this->assertStringContainsString('border-radius:12px', $style);
        $this->assertStringContainsString('overflow:hidden', $style);
    }

    // ── Visual Controls: Custom Shadow ──

    public function test_build_style_with_custom_shadow(): void
    {
        $style = BlockStyle::buildStyle(['visual' => [
            'shadowMode' => 'custom',
            'shadowCustom' => [
                'x' => '4px', 'y' => '8px', 'blur' => '16px', 'spread' => '2px',
                'color' => '#ff0000', 'opacity' => 50, 'inset' => false,
            ],
        ]]);
        $this->assertStringContainsString('box-shadow:', $style);
        $this->assertStringContainsString('rgba(255,0,0,0.50)', $style);
    }

    public function test_build_style_with_inset_shadow(): void
    {
        $style = BlockStyle::buildStyle(['visual' => [
            'shadowMode' => 'custom',
            'shadowCustom' => [
                'x' => '0px', 'y' => '2px', 'blur' => '8px', 'spread' => '0px',
                'color' => '#000000', 'opacity' => 20, 'inset' => true,
            ],
        ]]);
        $this->assertStringContainsString('inset', $style);
    }

    public function test_build_style_with_rgb_shadow_color(): void
    {
        $style = BlockStyle::buildStyle(['visual' => [
            'shadowMode' => 'custom',
            'shadowCustom' => [
                'x' => '0px', 'y' => '4px', 'blur' => '12px', 'spread' => '0px',
                'color' => 'rgba(100,200,50,1)', 'opacity' => 80,
            ],
        ]]);
        $this->assertStringContainsString('box-shadow:', $style);
        $this->assertStringNotContainsString('rgba(0,0,0', $style); // must NOT fall back to black
    }

    public function test_build_style_with_preset_shadow(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['boxShadow' => 'md']]);
        $this->assertStringContainsString('box-shadow:', $style);
    }

    // ── Visual Controls: Overflow ──

    public function test_build_style_with_overflow(): void
    {
        $style = BlockStyle::buildStyle(['visual' => ['overflow' => 'hidden']]);
        $this->assertStringContainsString('overflow:hidden', $style);
    }
}
