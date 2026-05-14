<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ChartBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ChartValidationTest extends TestCase
{
    private ChartBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ChartBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('chart', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_chartType_passes(): void
    {
        $this->assertTrue($this->validate(['chartType' => 'bar'])->passes());
    }

    public function test_invalid_chartType_fails(): void
    {
        $this->assertTrue($this->validate(['chartType' => '__invalid__'])->fails());
    }

    public function test_data_accepts_array(): void
    {
        $this->assertTrue($this->validate(['data' => []])->passes());
    }

    public function test_data_rejects_string(): void
    {
        $this->assertTrue($this->validate(['data' => 'not-array'])->fails());
    }

    public function test_title_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['title' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_showLegend_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showLegend' => true])->passes());
        $this->assertTrue($this->validate(['showLegend' => false])->passes());
    }
}
