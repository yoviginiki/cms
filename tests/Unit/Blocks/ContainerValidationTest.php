<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ContainerBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContainerValidationTest extends TestCase
{
    private ContainerBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ContainerBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('container', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_maxWidth_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['maxWidth' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_centered_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['centered' => true])->passes());
        $this->assertTrue($this->validate(['centered' => false])->passes());
    }
}
