<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\FootnoteBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FootnoteValidationTest extends TestCase
{
    private FootnoteBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new FootnoteBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('footnote', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_marker_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['marker' => str_repeat('a', 10 + 1)])->fails());
    }
}
