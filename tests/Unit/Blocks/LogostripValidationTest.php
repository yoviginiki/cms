<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\LogostripBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LogostripValidationTest extends TestCase
{
    private LogostripBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new LogostripBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('logostrip', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_logos_accepts_array(): void
    {
        $this->assertTrue($this->validate(['logos' => []])->passes());
    }

    public function test_logos_rejects_string(): void
    {
        $this->assertTrue($this->validate(['logos' => 'not-array'])->fails());
    }

    public function test_grayscale_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['grayscale' => true])->passes());
        $this->assertTrue($this->validate(['grayscale' => false])->passes());
    }

    public function test_columns_in_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1])->passes());
        $this->assertTrue($this->validate(['columns' => 8])->passes());
    }

    public function test_columns_out_of_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['columns' => 8 + 1])->fails());
    }

    public function test_gap_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['gap' => str_repeat('a', 20 + 1)])->fails());
    }
}
