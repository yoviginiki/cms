<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ModalBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ModalValidationTest extends TestCase
{
    private ModalBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ModalBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('modal', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_triggerText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['triggerText' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_title_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['title' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_valid_size_passes(): void
    {
        $this->assertTrue($this->validate(['size' => 'sm'])->passes());
    }

    public function test_invalid_size_fails(): void
    {
        $this->assertTrue($this->validate(['size' => '__invalid__'])->fails());
    }
}
