<?php

namespace Tests\Unit\SiteWizard;

use App\Services\SiteWizard\StyleProfileMapper;
use App\Services\ThemeWizard\TokenProfileValidator;
use PHPUnit\Framework\TestCase;

/**
 * The deterministic theme mapper's contract: whatever the extractor read off
 * the page — including hostile palettes — the output ALWAYS passes
 * TokenProfileValidator, and the enum buckets follow the measurements.
 */
class StyleProfileMapperTest extends TestCase
{
    private StyleProfileMapper $mapper;
    private TokenProfileValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TokenProfileValidator();
        $this->mapper = new StyleProfileMapper($this->validator);
    }

    private function signals(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Test Site',
            'body' => ['fontFamily' => 'Inter, sans-serif', 'fontSize' => '16px', 'color' => 'rgb(51, 51, 60)', 'background' => 'rgb(255, 255, 255)'],
            'h1' => ['fontFamily' => 'Inter, sans-serif', 'fontWeight' => '800', 'fontSize' => '48px', 'color' => 'rgb(10, 10, 20)'],
            'h2' => ['fontFamily' => 'Inter, sans-serif', 'fontWeight' => '700', 'fontSize' => '32px'],
            'link_color' => 'rgb(37, 99, 235)',
            'buttons' => [['background' => 'rgb(37, 99, 235)', 'color' => 'rgb(255, 255, 255)', 'radius' => '8px']],
            'background_histogram' => [['color' => 'rgb(255, 255, 255)', 'weight' => 0.9]],
            'shadow_ratio' => 0.1,
            'section_padding' => 64,
            'theme_color_meta' => null,
        ], $overrides);
    }

    public function test_typical_signals_produce_a_valid_profile(): void
    {
        $profile = $this->mapper->map($this->signals(), 'Test Site');

        $this->assertSame([], $this->validator->validate($profile));
        $this->assertSame('#2563eb', $profile['palette']['brand']);
        $this->assertSame('dramatic', $profile['typography']['scale']); // 48/16 = 3.0
        $this->assertSame('soft', $profile['radius']);
        $this->assertSame(800, $profile['typography']['heading_weight']);
    }

    public function test_hostile_white_on_white_is_repaired(): void
    {
        $profile = $this->mapper->map($this->signals([
            'body' => ['color' => 'rgb(255, 255, 255)', 'background' => 'rgb(255, 255, 255)'],
            'h1' => ['color' => 'rgb(250, 250, 250)'],
            'link_color' => 'rgb(254, 254, 254)',
            'buttons' => [['background' => 'rgb(255, 255, 255)', 'color' => 'rgb(255, 255, 255)', 'radius' => '0px']],
        ]), 'Hostile');

        $this->assertSame([], $this->validator->validate($profile));
    }

    public function test_empty_signals_still_produce_a_valid_profile(): void
    {
        $profile = $this->mapper->map([], 'Empty');

        $this->assertSame([], $this->validator->validate($profile));
    }

    public function test_dark_theme_survives_validation(): void
    {
        $profile = $this->mapper->map($this->signals([
            'body' => ['color' => 'rgb(226, 232, 240)', 'background' => 'rgb(15, 23, 42)'],
            'h1' => ['color' => 'rgb(248, 250, 252)'],
            'background_histogram' => [['color' => 'rgb(15, 23, 42)', 'weight' => 0.9], ['color' => 'rgb(30, 41, 59)', 'weight' => 0.1]],
        ]), 'Dark');

        $this->assertSame([], $this->validator->validate($profile));
        $this->assertSame('#0f172a', $profile['palette']['background']);
    }

    public function test_serif_fonts_are_classified_as_serif_character(): void
    {
        $profile = $this->mapper->map($this->signals([
            'body' => ['fontFamily' => '"Playfair Display", Georgia, serif'],
            'h1' => ['fontFamily' => '"Playfair Display", Georgia, serif'],
        ]), 'Serif');

        $this->assertStringContainsString('serif', $profile['typography']['display_character']);
    }

    public function test_enum_bucketing_follows_measurements(): void
    {
        $tight = $this->mapper->map($this->signals(['section_padding' => 24, 'shadow_ratio' => 0.0]), 'T');
        $this->assertSame('tight', $tight['spacing']);
        $this->assertSame('none', $tight['shadow']);

        $airy = $this->mapper->map($this->signals([
            'section_padding' => 120,
            'shadow_ratio' => 0.4,
            'buttons' => [['background' => 'rgb(37, 99, 235)', 'color' => 'rgb(255,255,255)', 'radius' => '24px']],
        ]), 'A');
        $this->assertSame('airy', $airy['spacing']);
        $this->assertSame('soft', $airy['shadow']);
        $this->assertSame('rounded', $airy['radius']);
    }

    public function test_transparent_and_malformed_colors_are_ignored(): void
    {
        $this->assertNull($this->mapper->cssColor('rgba(0, 0, 0, 0)'));
        $this->assertNull($this->mapper->cssColor('transparent'));
        $this->assertNull($this->mapper->cssColor(null));
        $this->assertSame('#aabbcc', $this->mapper->cssColor('#abc'));
        $this->assertSame('#ff8000', $this->mapper->cssColor('rgb(255, 128, 0)'));
    }
}
