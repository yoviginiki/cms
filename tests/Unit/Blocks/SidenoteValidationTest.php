<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SidenoteBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SidenoteValidationTest extends TestCase
{
    private SidenoteBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new SidenoteBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('sidenote', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_side_passes(): void
    {
        $this->assertTrue($this->validate(['side' => 'left'])->passes());
    }

    public function test_invalid_side_fails(): void
    {
        $this->assertTrue($this->validate(['side' => '__invalid__'])->fails());
    }
}
