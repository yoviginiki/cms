<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\CodeBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CodeValidationTest extends TestCase
{
    private CodeBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new CodeBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('code', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_language_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['language' => str_repeat('a', 30 + 1)])->fails());
    }

    public function test_show_line_numbers_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['show_line_numbers' => true])->passes());
        $this->assertTrue($this->validate(['show_line_numbers' => false])->passes());
    }
}
