<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\GalleryBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GalleryValidationTest extends TestCase
{
    private GalleryBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new GalleryBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('gallery', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_images_accepts_array(): void
    {
        $this->assertTrue($this->validate(['images' => []])->passes());
    }

    public function test_images_rejects_string(): void
    {
        $this->assertTrue($this->validate(['images' => 'not-array'])->fails());
    }

    public function test_valid_layout_passes(): void
    {
        $this->assertTrue($this->validate(['layout' => 'grid'])->passes());
    }

    public function test_invalid_layout_fails(): void
    {
        $this->assertTrue($this->validate(['layout' => '__invalid__'])->fails());
    }

    public function test_columns_in_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1])->passes());
        $this->assertTrue($this->validate(['columns' => 6])->passes());
    }

    public function test_columns_out_of_range(): void
    {
        $this->assertTrue($this->validate(['columns' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['columns' => 6 + 1])->fails());
    }

    public function test_gap_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['gap' => str_repeat('a', 20 + 1)])->fails());
    }
}
