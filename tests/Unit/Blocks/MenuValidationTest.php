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

    public function test_menuId_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['menuId' => str_repeat('a', 36 + 1)])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'horizontal'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_showLogo_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showLogo' => true])->passes());
        $this->assertTrue($this->validate(['showLogo' => false])->passes());
    }

    public function test_sticky_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['sticky' => true])->passes());
        $this->assertTrue($this->validate(['sticky' => false])->passes());
    }

    public function test_mobileBreakpoint_in_range(): void
    {
        $this->assertTrue($this->validate(['mobileBreakpoint' => 0])->passes());
        $this->assertTrue($this->validate(['mobileBreakpoint' => 1920])->passes());
    }

    public function test_mobileBreakpoint_out_of_range(): void
    {
        $this->assertTrue($this->validate(['mobileBreakpoint' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['mobileBreakpoint' => 1920 + 1])->fails());
    }
}
