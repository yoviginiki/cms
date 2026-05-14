<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\VideoBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VideoValidationTest extends TestCase
{
    private VideoBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new VideoBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('video', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_autoplay_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['autoplay' => true])->passes());
        $this->assertTrue($this->validate(['autoplay' => false])->passes());
    }

    public function test_muted_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['muted' => true])->passes());
        $this->assertTrue($this->validate(['muted' => false])->passes());
    }
}
