<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\IconBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IconValidationTest extends TestCase
{
    private IconBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new IconBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('icon', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_name_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['name' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_valid_size_passes(): void
    {
        $this->assertTrue($this->validate(['size' => 'sm'])->passes());
    }

    public function test_invalid_size_fails(): void
    {
        $this->assertTrue($this->validate(['size' => '__invalid__'])->fails());
    }

    public function test_color_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['color' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_valid_background_passes(): void
    {
        $this->assertTrue($this->validate(['background' => 'none'])->passes());
    }

    public function test_invalid_background_fails(): void
    {
        $this->assertTrue($this->validate(['background' => '__invalid__'])->fails());
    }

    public function test_backgroundColor_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['backgroundColor' => str_repeat('a', 50 + 1)])->fails());
    }
}
