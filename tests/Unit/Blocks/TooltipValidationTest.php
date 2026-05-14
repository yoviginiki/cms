<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TooltipBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TooltipValidationTest extends TestCase
{
    private TooltipBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TooltipBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('tooltip', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_triggerText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['triggerText' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_valid_position_passes(): void
    {
        $this->assertTrue($this->validate(['position' => 'top'])->passes());
    }

    public function test_invalid_position_fails(): void
    {
        $this->assertTrue($this->validate(['position' => '__invalid__'])->fails());
    }
}
