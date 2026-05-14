<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\CustomformBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomformValidationTest extends TestCase
{
    private CustomformBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new CustomformBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('customform', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_fields_accepts_array(): void
    {
        $this->assertTrue($this->validate(['fields' => []])->passes());
    }

    public function test_fields_rejects_string(): void
    {
        $this->assertTrue($this->validate(['fields' => 'not-array'])->fails());
    }

    public function test_submitText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['submitText' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_endpoint_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['endpoint' => 'javascript:alert(1)'])->fails());
    }
}
