<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ImagecaptionBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ImagecaptionValidationTest extends TestCase
{
    private ImagecaptionBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ImagecaptionBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('imagecaption', $this->def->type());
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

    public function test_valid_captionPosition_passes(): void
    {
        $this->assertTrue($this->validate(['captionPosition' => 'below'])->passes());
    }

    public function test_invalid_captionPosition_fails(): void
    {
        $this->assertTrue($this->validate(['captionPosition' => '__invalid__'])->fails());
    }
}
