<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\FullbleedBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FullbleedValidationTest extends TestCase
{
    private FullbleedBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new FullbleedBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('fullbleed', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_alt_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['alt' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_valid_overlayPosition_passes(): void
    {
        $this->assertTrue($this->validate(['overlayPosition' => 'center'])->passes());
    }

    public function test_invalid_overlayPosition_fails(): void
    {
        $this->assertTrue($this->validate(['overlayPosition' => '__invalid__'])->fails());
    }

    public function test_minHeight_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['minHeight' => str_repeat('a', 20 + 1)])->fails());
    }
}
