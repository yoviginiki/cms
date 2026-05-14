<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\HeadingBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HeadingValidationTest extends TestCase
{
    private HeadingBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new HeadingBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('heading', $this->def->type());
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

    public function test_valid_level_passes(): void
    {
        $this->assertTrue($this->validate(['level' => 'h1'])->passes());
    }

    public function test_invalid_level_fails(): void
    {
        $this->assertTrue($this->validate(['level' => '__invalid__'])->fails());
    }
}
