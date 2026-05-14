<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PullquoteBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PullquoteValidationTest extends TestCase
{
    private PullquoteBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PullquoteBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('pullquote', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_attribution_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['attribution' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'large-text'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }
}
