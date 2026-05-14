<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ScrollPageBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ScrollPageValidationTest extends TestCase
{
    private ScrollPageBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ScrollPageBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('scroll_page', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_typography_accepts_array(): void
    {
        $this->assertTrue($this->validate(['typography' => []])->passes());
    }

    public function test_typography_rejects_string(): void
    {
        $this->assertTrue($this->validate(['typography' => 'not-array'])->fails());
    }

    public function test_palette_accepts_array(): void
    {
        $this->assertTrue($this->validate(['palette' => []])->passes());
    }

    public function test_palette_rejects_string(): void
    {
        $this->assertTrue($this->validate(['palette' => 'not-array'])->fails());
    }

    public function test_layout_accepts_array(): void
    {
        $this->assertTrue($this->validate(['layout' => []])->passes());
    }

    public function test_layout_rejects_string(): void
    {
        $this->assertTrue($this->validate(['layout' => 'not-array'])->fails());
    }

    public function test_backdrop_accepts_array(): void
    {
        $this->assertTrue($this->validate(['backdrop' => []])->passes());
    }

    public function test_backdrop_rejects_string(): void
    {
        $this->assertTrue($this->validate(['backdrop' => 'not-array'])->fails());
    }

    public function test_mouseEffect_accepts_array(): void
    {
        $this->assertTrue($this->validate(['mouseEffect' => []])->passes());
    }

    public function test_mouseEffect_rejects_string(): void
    {
        $this->assertTrue($this->validate(['mouseEffect' => 'not-array'])->fails());
    }

    public function test_reveal_accepts_array(): void
    {
        $this->assertTrue($this->validate(['reveal' => []])->passes());
    }

    public function test_reveal_rejects_string(): void
    {
        $this->assertTrue($this->validate(['reveal' => 'not-array'])->fails());
    }

    public function test_responsive_accepts_array(): void
    {
        $this->assertTrue($this->validate(['responsive' => []])->passes());
    }

    public function test_responsive_rejects_string(): void
    {
        $this->assertTrue($this->validate(['responsive' => 'not-array'])->fails());
    }

    public function test_scrollHint_accepts_array(): void
    {
        $this->assertTrue($this->validate(['scrollHint' => []])->passes());
    }

    public function test_scrollHint_rejects_string(): void
    {
        $this->assertTrue($this->validate(['scrollHint' => 'not-array'])->fails());
    }

    public function test_pages_accepts_array(): void
    {
        $this->assertTrue($this->validate(['pages' => []])->passes());
    }

    public function test_pages_rejects_string(): void
    {
        $this->assertTrue($this->validate(['pages' => 'not-array'])->fails());
    }
}
