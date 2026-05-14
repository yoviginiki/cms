<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\DropcapBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DropcapValidationTest extends TestCase
{
    private DropcapBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new DropcapBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('dropcap', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_capSize_in_range(): void
    {
        $this->assertTrue($this->validate(['capSize' => 1])->passes());
        $this->assertTrue($this->validate(['capSize' => 10])->passes());
    }

    public function test_capSize_out_of_range(): void
    {
        $this->assertTrue($this->validate(['capSize' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['capSize' => 10 + 1])->fails());
    }

    public function test_capColor_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['capColor' => str_repeat('a', 50 + 1)])->fails());
    }
}
