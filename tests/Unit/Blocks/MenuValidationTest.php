<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\MenuBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MenuValidationTest extends TestCase
{
    private MenuBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new MenuBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('menu', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_source_passes(): void
    {
        $this->assertTrue($this->validate(['source' => 'system'])->passes());
        $this->assertTrue($this->validate(['source' => 'custom'])->passes());
    }

    public function test_invalid_source_fails(): void
    {
        $this->assertTrue($this->validate(['source' => 'invalid'])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'horizontal'])->passes());
        $this->assertTrue($this->validate(['style' => 'vertical'])->passes());
        $this->assertTrue($this->validate(['style' => 'hamburger'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => 'dropdown'])->fails());
    }

    public function test_custom_items_valid(): void
    {
        $this->assertTrue($this->validate([
            'customItems' => [
                ['label' => 'Home', 'url' => '/', 'target' => '_self'],
            ],
        ])->passes());
    }

    public function test_custom_items_blocks_javascript(): void
    {
        $this->assertTrue($this->validate([
            'customItems' => [
                ['label' => 'XSS', 'url' => 'javascript:alert(1)', 'target' => '_self'],
            ],
        ])->fails());
    }

    public function test_valid_colors(): void
    {
        $this->assertTrue($this->validate(['bgColor' => '#ff0000'])->passes());
        $this->assertTrue($this->validate(['textColor' => '#333'])->passes());
        $this->assertTrue($this->validate(['hoverColor' => 'rgba(0,0,0,0.5)'])->passes());
    }

    public function test_invalid_color_fails(): void
    {
        $this->assertTrue($this->validate(['bgColor' => 'url(evil)'])->fails());
    }

    public function test_mobile_breakpoint_range(): void
    {
        $this->assertTrue($this->validate(['mobileBreakpoint' => 768])->passes());
        $this->assertTrue($this->validate(['mobileBreakpoint' => -1])->fails());
        $this->assertTrue($this->validate(['mobileBreakpoint' => 1921])->fails());
    }

    public function test_sticky_boolean(): void
    {
        $this->assertTrue($this->validate(['sticky' => true])->passes());
        $this->assertTrue($this->validate(['sticky' => false])->passes());
    }

    public function test_menuId_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['menuId' => str_repeat('a', 37)])->fails());
    }
}
