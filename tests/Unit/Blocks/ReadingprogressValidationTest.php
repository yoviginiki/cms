<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ReadingprogressBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ReadingprogressValidationTest extends TestCase
{
    private ReadingprogressBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ReadingprogressBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('readingprogress', $this->def->type());
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
        $this->assertTrue($this->validate(['style' => 'top-bar'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_color_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['color' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_height_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['height' => str_repeat('a', 20 + 1)])->fails());
    }
}
