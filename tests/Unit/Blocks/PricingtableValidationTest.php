<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PricingtableBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PricingtableValidationTest extends TestCase
{
    private PricingtableBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PricingtableBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('pricingtable', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_plans_accepts_array(): void
    {
        $this->assertTrue($this->validate(['plans' => []])->passes());
    }

    public function test_plans_rejects_string(): void
    {
        $this->assertTrue($this->validate(['plans' => 'not-array'])->fails());
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
}
