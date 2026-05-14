<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\AudioBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AudioValidationTest extends TestCase
{
    private AudioBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new AudioBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('audio', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_title_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['title' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_artist_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['artist' => str_repeat('a', 255 + 1)])->fails());
    }
}
