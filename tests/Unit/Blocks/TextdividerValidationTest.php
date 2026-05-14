<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TextdividerBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TextdividerValidationTest extends TestCase
{
    private TextdividerBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TextdividerBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('textdivider', $this->def->type());
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
        $this->assertTrue($this->validate(['style' => 'line'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_customSymbol_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['customSymbol' => str_repeat('a', 10 + 1)])->fails());
    }

    public function test_valid_width_passes(): void
    {
        $this->assertTrue($this->validate(['width' => 'full'])->passes());
    }

    public function test_invalid_width_fails(): void
    {
        $this->assertTrue($this->validate(['width' => '__invalid__'])->fails());
    }
}
