<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ButtonBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ButtonValidationTest extends TestCase
{
    private ButtonBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ButtonBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('button', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_text_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['text' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'primary'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_valid_size_passes(): void
    {
        $this->assertTrue($this->validate(['size' => 'sm'])->passes());
    }

    public function test_invalid_size_fails(): void
    {
        $this->assertTrue($this->validate(['size' => '__invalid__'])->fails());
    }

    public function test_valid_target_passes(): void
    {
        $this->assertTrue($this->validate(['target' => '_self'])->passes());
    }

    public function test_invalid_target_fails(): void
    {
        $this->assertTrue($this->validate(['target' => '__invalid__'])->fails());
    }
}
