<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\AnchormenuBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AnchormenuValidationTest extends TestCase
{
    private AnchormenuBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new AnchormenuBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('anchormenu', $this->def->type());
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

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'horizontal'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_sticky_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['sticky' => true])->passes());
        $this->assertTrue($this->validate(['sticky' => false])->passes());
    }

    public function test_smooth_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['smooth' => true])->passes());
        $this->assertTrue($this->validate(['smooth' => false])->passes());
    }

    public function test_offset_in_range(): void
    {
        $this->assertTrue($this->validate(['offset' => 0])->passes());
        $this->assertTrue($this->validate(['offset' => 500])->passes());
    }

    public function test_offset_out_of_range(): void
    {
        $this->assertTrue($this->validate(['offset' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['offset' => 500 + 1])->fails());
    }

    public function test_activeHighlight_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['activeHighlight' => true])->passes());
        $this->assertTrue($this->validate(['activeHighlight' => false])->passes());
    }
}
