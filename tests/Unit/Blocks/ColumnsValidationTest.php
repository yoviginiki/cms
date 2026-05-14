<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ColumnsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ColumnsValidationTest extends TestCase
{
    private ColumnsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ColumnsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('columns', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_column_count_in_range(): void
    {
        $this->assertTrue($this->validate(['column_count' => 2])->passes());
        $this->assertTrue($this->validate(['column_count' => 6])->passes());
    }

    public function test_column_count_out_of_range(): void
    {
        $this->assertTrue($this->validate(['column_count' => 2 - 1])->fails());
        $this->assertTrue($this->validate(['column_count' => 6 + 1])->fails());
    }

    public function test_valid_gap_passes(): void
    {
        $this->assertTrue($this->validate(['gap' => 'none'])->passes());
    }

    public function test_invalid_gap_fails(): void
    {
        $this->assertTrue($this->validate(['gap' => '__invalid__'])->fails());
    }
}
