<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TocBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TocValidationTest extends TestCase
{
    private TocBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TocBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('toc', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_maxDepth_in_range(): void
    {
        $this->assertTrue($this->validate(['maxDepth' => 1])->passes());
        $this->assertTrue($this->validate(['maxDepth' => 6])->passes());
    }

    public function test_maxDepth_out_of_range(): void
    {
        $this->assertTrue($this->validate(['maxDepth' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['maxDepth' => 6 + 1])->fails());
    }

    public function test_style_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['style' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_sticky_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['sticky' => true])->passes());
        $this->assertTrue($this->validate(['sticky' => false])->passes());
    }
}
