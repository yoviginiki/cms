<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\CaptionBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CaptionValidationTest extends TestCase
{
    private CaptionBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new CaptionBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('caption', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_prefix_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['prefix' => str_repeat('a', 20 + 1)])->fails());
    }
}
