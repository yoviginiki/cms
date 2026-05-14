<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TimelineBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TimelineValidationTest extends TestCase
{
    private TimelineBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TimelineBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('timeline', $this->def->type());
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

    public function test_valid_layout_passes(): void
    {
        $this->assertTrue($this->validate(['layout' => 'left'])->passes());
    }

    public function test_invalid_layout_fails(): void
    {
        $this->assertTrue($this->validate(['layout' => '__invalid__'])->fails());
    }

    public function test_lineStyle_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['lineStyle' => str_repeat('a', 20 + 1)])->fails());
    }
}
