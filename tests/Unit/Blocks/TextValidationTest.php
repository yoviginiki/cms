<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TextBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TextValidationTest extends TestCase
{
    private TextBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TextBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('text', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }
}
