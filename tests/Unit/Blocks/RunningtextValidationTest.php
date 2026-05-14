<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\RunningtextBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RunningtextValidationTest extends TestCase
{
    private RunningtextBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new RunningtextBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('runningtext', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_columns_in_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1])->passes());
        $this->assertTrue($this->validate(['columns' => 4])->passes());
    }

    public function test_columns_out_of_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['columns' => 4 + 1])->fails());
    }

    public function test_columnGap_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['columnGap' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_columnRule_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['columnRule' => true])->passes());
        $this->assertTrue($this->validate(['columnRule' => false])->passes());
    }
}
