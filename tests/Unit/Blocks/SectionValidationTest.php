<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SectionBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SectionValidationTest extends TestCase
{
    private SectionBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new SectionBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('section', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_background_color_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['background_color' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_valid_padding_passes(): void
    {
        $this->assertTrue($this->validate(['padding' => 'none'])->passes());
    }

    public function test_invalid_padding_fails(): void
    {
        $this->assertTrue($this->validate(['padding' => '__invalid__'])->fails());
    }

    public function test_anchor_id_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['anchor_id' => str_repeat('a', 100 + 1)])->fails());
    }
}
