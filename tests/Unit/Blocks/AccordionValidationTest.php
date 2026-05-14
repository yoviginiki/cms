<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\AccordionBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AccordionValidationTest extends TestCase
{
    private AccordionBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new AccordionBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('accordion', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_items_accepts_array(): void
    {
        $this->assertTrue($this->validate(['items' => []])->passes());
    }

    public function test_items_rejects_string(): void
    {
        $this->assertTrue($this->validate(['items' => 'not-array'])->fails());
    }
}
