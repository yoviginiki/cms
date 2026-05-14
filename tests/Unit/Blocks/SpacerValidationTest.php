<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SpacerBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SpacerValidationTest extends TestCase
{
    private SpacerBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new SpacerBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('spacer', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_height_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['height' => str_repeat('a', 20 + 1)])->fails());
    }
}
