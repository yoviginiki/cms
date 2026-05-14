<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\MapBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MapValidationTest extends TestCase
{
    private MapBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new MapBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('map', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_zoom_in_range(): void
    {
        $this->assertTrue($this->validate(['zoom' => 1])->passes());
        $this->assertTrue($this->validate(['zoom' => 20])->passes());
    }

    public function test_zoom_out_of_range(): void
    {
        $this->assertTrue($this->validate(['zoom' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['zoom' => 20 + 1])->fails());
    }

    public function test_markerLabel_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['markerLabel' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_height_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['height' => str_repeat('a', 20 + 1)])->fails());
    }
}
