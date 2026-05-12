<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\HeroBlockDefinition;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HeroValidationTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = (new HeroBlockDefinition())->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make(array_merge(['title' => 'Test'], $data), $this->rules);
    }

    // ── headlineTag ──────────────────────────────────────────────

    #[DataProvider('validHeadlineTagProvider')]
    public function test_valid_headlineTag_passes(string $tag): void
    {
        $this->assertTrue($this->validate(['headlineTag' => $tag])->passes());
    }

    public static function validHeadlineTagProvider(): array
    {
        return [['h1'], ['h2'], ['h3']];
    }

    #[DataProvider('invalidHeadlineTagProvider')]
    public function test_invalid_headlineTag_fails(string $tag): void
    {
        $this->assertTrue($this->validate(['headlineTag' => $tag])->fails());
    }

    public static function invalidHeadlineTagProvider(): array
    {
        return [['h4'], ['div'], ['script']];
    }

    // ── textAlignment ────────────────────────────────────────────

    #[DataProvider('validTextAlignmentProvider')]
    public function test_valid_textAlignment_passes(string $val): void
    {
        $this->assertTrue($this->validate(['textAlignment' => $val])->passes());
    }

    public static function validTextAlignmentProvider(): array
    {
        return [['left'], ['center'], ['right']];
    }

    public function test_invalid_textAlignment_fails(): void
    {
        $this->assertTrue($this->validate(['textAlignment' => 'justify'])->fails());
    }

    // ── sectionHeight ────────────────────────────────────────────

    #[DataProvider('validSectionHeightProvider')]
    public function test_valid_sectionHeight_passes(string $val): void
    {
        $this->assertTrue($this->validate(['sectionHeight' => $val])->passes());
    }

    public static function validSectionHeightProvider(): array
    {
        return [['auto'], ['sm'], ['md'], ['lg'], ['fullscreen']];
    }

    public function test_invalid_sectionHeight_fails(): void
    {
        $this->assertTrue($this->validate(['sectionHeight' => 'xl'])->fails());
    }

    // ── contentMaxWidth ──────────────────────────────────────────

    #[DataProvider('validContentMaxWidthProvider')]
    public function test_valid_contentMaxWidth_passes(string $val): void
    {
        $this->assertTrue($this->validate(['contentMaxWidth' => $val])->passes());
    }

    public static function validContentMaxWidthProvider(): array
    {
        return [['800px'], ['60rem'], ['100%']];
    }

    #[DataProvider('invalidContentMaxWidthProvider')]
    public function test_invalid_contentMaxWidth_fails(string $val): void
    {
        $this->assertTrue($this->validate(['contentMaxWidth' => $val])->fails());
    }

    public static function invalidContentMaxWidthProvider(): array
    {
        return [['javascript:'], ['800'], ['auto']];
    }

    // ── headlineColor ────────────────────────────────────────────

    #[DataProvider('validHeadlineColorProvider')]
    public function test_valid_headlineColor_passes(string $val): void
    {
        $this->assertTrue($this->validate(['headlineColor' => $val])->passes());
    }

    public static function validHeadlineColorProvider(): array
    {
        return [['#fff'], ['#1e40af'], ['rgba(0,0,0,0.5)']];
    }

    #[DataProvider('invalidHeadlineColorProvider')]
    public function test_invalid_headlineColor_fails(string $val): void
    {
        $this->assertTrue($this->validate(['headlineColor' => $val])->fails());
    }

    public static function invalidHeadlineColorProvider(): array
    {
        return [['<script>'], ['expression()']];
    }

    // ── headlineWeight ───────────────────────────────────────────

    #[DataProvider('validHeadlineWeightProvider')]
    public function test_valid_headlineWeight_passes(string $val): void
    {
        $this->assertTrue($this->validate(['headlineWeight' => $val])->passes());
    }

    public static function validHeadlineWeightProvider(): array
    {
        return [['400'], ['700']];
    }

    #[DataProvider('invalidHeadlineWeightProvider')]
    public function test_invalid_headlineWeight_fails(string $val): void
    {
        $this->assertTrue($this->validate(['headlineWeight' => $val])->fails());
    }

    public static function invalidHeadlineWeightProvider(): array
    {
        return [['350'], ['bold']];
    }

    // ── adaptiveTextColor ────────────────────────────────────────

    public function test_adaptiveTextColor_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['adaptiveTextColor' => true])->passes());
        $this->assertTrue($this->validate(['adaptiveTextColor' => false])->passes());
    }

    // ── mediaLoading ─────────────────────────────────────────────

    public function test_mediaLoading_accepts_eager(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'eager'])->passes());
    }

    public function test_mediaLoading_accepts_lazy(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'lazy'])->passes());
    }

    public function test_mediaLoading_rejects_invalid(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'auto'])->fails());
    }

    // ── ctaVariant ──────────────────────────────────────────────

    #[DataProvider('validCtaVariantProvider')]
    public function test_valid_ctaVariant_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaVariant' => $val])->passes());
    }

    public static function validCtaVariantProvider(): array
    {
        return [['filled'], ['outline'], ['ghost'], ['link']];
    }

    #[DataProvider('invalidCtaVariantProvider')]
    public function test_invalid_ctaVariant_fails(string $val): void
    {
        $this->assertTrue($this->validate(['ctaVariant' => $val])->fails());
    }

    public static function invalidCtaVariantProvider(): array
    {
        return [['primary'], ['danger'], ['<script>']];
    }

    // ── ctaSize ─────────────────────────────────────────────────

    #[DataProvider('validCtaSizeProvider')]
    public function test_valid_ctaSize_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaSize' => $val])->passes());
    }

    public static function validCtaSizeProvider(): array
    {
        return [['sm'], ['md'], ['lg']];
    }

    public function test_invalid_ctaSize_fails(): void
    {
        $this->assertTrue($this->validate(['ctaSize' => 'xl'])->fails());
    }

    // ── ctaAlign ────────────────────────────────────────────────

    #[DataProvider('validCtaAlignProvider')]
    public function test_valid_ctaAlign_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaAlign' => $val])->passes());
    }

    public static function validCtaAlignProvider(): array
    {
        return [['left'], ['center'], ['right'], ['']];
    }

    public function test_invalid_ctaAlign_fails(): void
    {
        $this->assertTrue($this->validate(['ctaAlign' => 'justify'])->fails());
    }

    // ── ctaBgColor ──────────────────────────────────────────────

    #[DataProvider('validCtaColorProvider')]
    public function test_valid_ctaBgColor_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaBgColor' => $val])->passes());
    }

    public static function validCtaColorProvider(): array
    {
        return [['#fff'], ['#3b82f6'], ['rgba(0,0,0,0.5)']];
    }

    #[DataProvider('invalidCtaColorProvider')]
    public function test_invalid_ctaBgColor_fails(string $val): void
    {
        $this->assertTrue($this->validate(['ctaBgColor' => $val])->fails());
    }

    public static function invalidCtaColorProvider(): array
    {
        return [['<script>'], ['expression()'], ['url(evil)']];
    }

    // ── ctaTextColor ────────────────────────────────────────────

    public function test_valid_ctaTextColor_passes(): void
    {
        $this->assertTrue($this->validate(['ctaTextColor' => '#ffffff'])->passes());
    }

    public function test_invalid_ctaTextColor_fails(): void
    {
        $this->assertTrue($this->validate(['ctaTextColor' => 'javascript:void'])->fails());
    }

    // ── ctaBorderColor ──────────────────────────────────────────

    public function test_valid_ctaBorderColor_passes(): void
    {
        $this->assertTrue($this->validate(['ctaBorderColor' => '#000'])->passes());
    }

    public function test_invalid_ctaBorderColor_fails(): void
    {
        $this->assertTrue($this->validate(['ctaBorderColor' => '<img src=x>'])->fails());
    }

    // ── ctaBorderWidth ──────────────────────────────────────────

    #[DataProvider('validCtaDimensionProvider')]
    public function test_valid_ctaBorderWidth_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaBorderWidth' => $val])->passes());
    }

    public static function validCtaDimensionProvider(): array
    {
        return [['2px'], ['0.5rem'], ['1em']];
    }

    #[DataProvider('invalidCtaDimensionProvider')]
    public function test_invalid_ctaBorderWidth_fails(string $val): void
    {
        $this->assertTrue($this->validate(['ctaBorderWidth' => $val])->fails());
    }

    public static function invalidCtaDimensionProvider(): array
    {
        return [['javascript:'], ['expression(1)'], ['10']];
    }

    // ── ctaBorderRadius ─────────────────────────────────────────

    #[DataProvider('validCtaBorderRadiusProvider')]
    public function test_valid_ctaBorderRadius_passes(string $val): void
    {
        $this->assertTrue($this->validate(['ctaBorderRadius' => $val])->passes());
    }

    public static function validCtaBorderRadiusProvider(): array
    {
        return [['8px'], ['0.375rem'], ['50%']];
    }

    public function test_invalid_ctaBorderRadius_fails(): void
    {
        $this->assertTrue($this->validate(['ctaBorderRadius' => 'url(x)'])->fails());
    }

    // ── backward compatibility ──────────────────────────────────

    public function test_old_hero_data_without_cta_style_fields_passes(): void
    {
        // Simulate old data that has no CTA style fields at all
        $v = $this->validate([
            'ctaText' => 'Learn More',
            'ctaUrl' => 'https://example.com',
        ]);
        $this->assertTrue($v->passes(), 'Old Hero data without CTA style fields must pass validation');
    }

    public function test_empty_cta_style_fields_pass(): void
    {
        $v = $this->validate([
            'ctaBgColor' => '',
            'ctaTextColor' => '',
            'ctaBorderColor' => '',
            'ctaBorderWidth' => '',
            'ctaBorderRadius' => '',
        ]);
        // Nullable fields accept empty strings
        $this->assertTrue($v->passes());
    }

    // ── bg_gradient_stops validation ────────────────────────────

    public function test_valid_gradient_stops_pass(): void
    {
        $v = $this->validate([
            'bg_type' => 'gradient',
            'bg_gradient_stops' => [
                ['color' => '#3b82f6', 'position' => 0],
                ['color' => '#8b5cf6', 'position' => 100],
            ],
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_gradient_stops_with_invalid_color_fails(): void
    {
        $v = $this->validate([
            'bg_gradient_stops' => [
                ['color' => '<script>', 'position' => 0],
            ],
        ]);
        $this->assertTrue($v->fails());
    }

    public function test_gradient_stops_with_missing_color_fails(): void
    {
        $v = $this->validate([
            'bg_gradient_stops' => [
                ['position' => 50],
            ],
        ]);
        $this->assertTrue($v->fails());
    }

    public function test_empty_gradient_stops_array_passes(): void
    {
        $v = $this->validate([
            'bg_type' => 'gradient',
            'bg_gradient_stops' => [],
        ]);
        $this->assertTrue($v->passes());
    }

    // ── responsive overrides ────────────────────────────────────

    public function test_valid_responsive_tablet_overrides_pass(): void
    {
        $v = $this->validate([
            'responsive' => [
                'tablet' => [
                    'textAlignment' => 'left',
                    'sectionHeight' => 'sm',
                    'contentMaxWidth' => '600px',
                ],
            ],
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_valid_responsive_mobile_overrides_pass(): void
    {
        $v = $this->validate([
            'responsive' => [
                'mobile' => [
                    'textAlignment' => 'center',
                    'sectionHeight' => 'auto',
                ],
            ],
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_invalid_responsive_tablet_alignment_fails(): void
    {
        $v = $this->validate([
            'responsive' => [
                'tablet' => ['textAlignment' => 'justify'],
            ],
        ]);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_responsive_mobile_height_fails(): void
    {
        $v = $this->validate([
            'responsive' => [
                'mobile' => ['sectionHeight' => 'huge'],
            ],
        ]);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_responsive_contentMaxWidth_fails(): void
    {
        $v = $this->validate([
            'responsive' => [
                'tablet' => ['contentMaxWidth' => 'javascript:alert(1)'],
            ],
        ]);
        $this->assertTrue($v->fails());
    }

    public function test_empty_responsive_object_passes(): void
    {
        $v = $this->validate([
            'responsive' => [],
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_old_hero_data_without_responsive_passes(): void
    {
        $v = $this->validate([
            'textAlignment' => 'center',
            'sectionHeight' => 'md',
        ]);
        $this->assertTrue($v->passes(), 'Old Hero data without responsive object must pass');
    }

    // ── content box validation ──────────────────────────────────

    public function test_valid_content_box_fields_pass(): void
    {
        $v = $this->validate([
            'contentBoxEnabled' => true,
            'contentBoxBgColor' => '#ffffff',
            'contentBoxOpacity' => 80,
            'contentBoxBorderRadius' => '0.75rem',
            'contentBoxBorderColor' => '#000000',
            'contentBoxBorderWidth' => '1px',
            'contentBoxShadow' => 'md',
            'contentBoxPadding' => '2rem',
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_content_box_opacity_out_of_range_fails(): void
    {
        $v = $this->validate(['contentBoxOpacity' => 150]);
        $this->assertTrue($v->fails());
    }

    public function test_content_box_unsafe_color_fails(): void
    {
        $v = $this->validate(['contentBoxBgColor' => '<script>']);
        $this->assertTrue($v->fails());
    }

    public function test_content_box_unsafe_dimension_fails(): void
    {
        $v = $this->validate(['contentBoxPadding' => 'expression(alert(1))']);
        $this->assertTrue($v->fails());
    }

    public function test_content_box_invalid_shadow_fails(): void
    {
        $v = $this->validate(['contentBoxShadow' => 'xl']);
        $this->assertTrue($v->fails());
    }

    public function test_old_hero_data_without_content_box_passes(): void
    {
        $v = $this->validate([]);
        $this->assertTrue($v->passes(), 'Old Hero data without contentBox fields must pass');
    }

    // ── section border & shadow validation ──────────────────────

    public function test_valid_section_border_passes(): void
    {
        $v = $this->validate([
            'sectionBorderWidth' => '2px',
            'sectionBorderColor' => '#333333',
            'sectionBorderStyle' => 'solid',
            'sectionBorderRadius' => '0.75rem',
        ]);
        $this->assertTrue($v->passes());
    }

    public function test_unsafe_section_border_width_fails(): void
    {
        $v = $this->validate(['sectionBorderWidth' => 'expression(alert(1))']);
        $this->assertTrue($v->fails());
    }

    public function test_unsafe_section_border_color_fails(): void
    {
        $v = $this->validate(['sectionBorderColor' => '<script>']);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_section_border_style_fails(): void
    {
        $v = $this->validate(['sectionBorderStyle' => 'double']);
        $this->assertTrue($v->fails());
    }

    #[DataProvider('validSectionShadowProvider')]
    public function test_valid_section_shadow_passes(string $val): void
    {
        $this->assertTrue($this->validate(['sectionShadow' => $val])->passes());
    }

    public static function validSectionShadowProvider(): array
    {
        return [['subtle'], ['medium'], ['large'], ['glow'], ['']];
    }

    public function test_invalid_section_shadow_fails(): void
    {
        $v = $this->validate(['sectionShadow' => 'xl']);
        $this->assertTrue($v->fails());
    }

    public function test_old_hero_data_without_section_border_passes(): void
    {
        $v = $this->validate([]);
        $this->assertTrue($v->passes(), 'Old Hero data without section border/shadow must pass');
    }

    // ── bg_scroll_effect validation ─────────────────────────────

    #[DataProvider('validScrollEffectProvider')]
    public function test_valid_scroll_effect_passes(string $val): void
    {
        $this->assertTrue($this->validate(['bg_scroll_effect' => $val])->passes());
    }

    public static function validScrollEffectProvider(): array
    {
        return [['none'], ['fixed'], ['parallax'], ['zoom']];
    }

    public function test_invalid_scroll_effect_fails(): void
    {
        $this->assertTrue($this->validate(['bg_scroll_effect' => 'bounce'])->fails());
    }

    public function test_section_shadow_none_passes(): void
    {
        $this->assertTrue($this->validate(['sectionShadow' => 'none'])->passes());
    }
}
