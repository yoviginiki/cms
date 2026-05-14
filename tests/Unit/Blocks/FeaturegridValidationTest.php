<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\FeaturegridBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FeaturegridValidationTest extends TestCase
{
    private FeaturegridBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new FeaturegridBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('featuregrid', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_items_accepts_array(): void
    {
        $this->assertTrue($this->validate(['items' => []])->passes());
    }

    public function test_items_rejects_string(): void
    {
        $this->assertTrue($this->validate(['items' => 'not-array'])->fails());
    }

    public function test_columns_in_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1])->passes());
        $this->assertTrue($this->validate(['columns' => 6])->passes());
    }

    public function test_columns_out_of_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['columns' => 6 + 1])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'icon-top'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    // ── Card Visual Controls ──

    public function test_card_bg_color_valid(): void
    {
        $this->assertTrue($this->validate(['cardBgColor' => '#ff0000'])->passes());
    }

    public function test_card_bg_color_rejects_unsafe(): void
    {
        $this->assertTrue($this->validate(['cardBgColor' => 'url(evil)'])->fails());
    }

    public function test_card_shadow_preset(): void
    {
        $this->assertTrue($this->validate(['cardShadow' => 'medium'])->passes());
        $this->assertTrue($this->validate(['cardShadowMode' => 'preset'])->passes());
    }

    public function test_card_shadow_custom(): void
    {
        $this->assertTrue($this->validate([
            'cardShadowMode' => 'custom',
            'cardShadowCustom' => ['x' => '4px', 'y' => '8px', 'blur' => '16px', 'color' => '#000000', 'opacity' => 50],
        ])->passes());
    }

    public function test_card_shadow_custom_rejects_unsafe(): void
    {
        $this->assertTrue($this->validate(['cardShadowCustom' => ['x' => 'expression(1)']])->fails());
    }

    public function test_card_border_radius_per_corner(): void
    {
        $this->assertTrue($this->validate(['cardBorderRadius' => ['topLeft' => '10px', 'topRight' => '0']])->passes());
    }

    public function test_typography_colors_valid(): void
    {
        $this->assertTrue($this->validate(['titleColor' => '#333', 'descColor' => '#666', 'iconColor' => '#3b82f6'])->passes());
    }

    public function test_icon_size_valid(): void
    {
        $this->assertTrue($this->validate(['iconSize' => '2rem'])->passes());
    }
}
