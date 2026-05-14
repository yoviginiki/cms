<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TableBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TableValidationTest extends TestCase
{
    private TableBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TableBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('table', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_headers_accepts_array(): void
    {
        $this->assertTrue($this->validate(['headers' => []])->passes());
    }

    public function test_headers_rejects_string(): void
    {
        $this->assertTrue($this->validate(['headers' => 'not-array'])->fails());
    }

    public function test_rows_accepts_array(): void
    {
        $this->assertTrue($this->validate(['rows' => []])->passes());
    }

    public function test_rows_rejects_string(): void
    {
        $this->assertTrue($this->validate(['rows' => 'not-array'])->fails());
    }

    public function test_striped_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['striped' => true])->passes());
        $this->assertTrue($this->validate(['striped' => false])->passes());
    }

    public function test_compact_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['compact' => true])->passes());
        $this->assertTrue($this->validate(['compact' => false])->passes());
    }
}
