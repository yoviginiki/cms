<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SharebuttonsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SharebuttonsValidationTest extends TestCase
{
    private SharebuttonsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new SharebuttonsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('sharebuttons', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_platforms_accepts_array(): void
    {
        $this->assertTrue($this->validate(['platforms' => []])->passes());
    }

    public function test_platforms_rejects_string(): void
    {
        $this->assertTrue($this->validate(['platforms' => 'not-array'])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'icons'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_showLabels_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showLabels' => true])->passes());
        $this->assertTrue($this->validate(['showLabels' => false])->passes());
    }
}
