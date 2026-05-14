<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\GridBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GridValidationTest extends TestCase
{
    private GridBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new GridBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('grid', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_templateColumns_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['templateColumns' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_templateRows_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['templateRows' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_gap_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['gap' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_valid_autoFlow_passes(): void
    {
        $this->assertTrue($this->validate(['autoFlow' => 'row'])->passes());
    }

    public function test_invalid_autoFlow_fails(): void
    {
        $this->assertTrue($this->validate(['autoFlow' => '__invalid__'])->fails());
    }
}
