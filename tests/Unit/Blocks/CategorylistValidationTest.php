<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\CategorylistBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CategorylistValidationTest extends TestCase
{
    private CategorylistBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new CategorylistBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('categorylist', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'links'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_showCount_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showCount' => true])->passes());
        $this->assertTrue($this->validate(['showCount' => false])->passes());
    }

    public function test_parentOnly_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['parentOnly' => true])->passes());
        $this->assertTrue($this->validate(['parentOnly' => false])->passes());
    }
}
