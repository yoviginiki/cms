<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\GroupBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GroupValidationTest extends TestCase
{
    private GroupBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new GroupBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('group', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_tag_passes(): void
    {
        $this->assertTrue($this->validate(['tag' => 'div'])->passes());
    }

    public function test_invalid_tag_fails(): void
    {
        $this->assertTrue($this->validate(['tag' => '__invalid__'])->fails());
    }
}
