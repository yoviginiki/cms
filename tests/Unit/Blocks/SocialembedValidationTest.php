<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\SocialembedBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SocialembedValidationTest extends TestCase
{
    private SocialembedBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new SocialembedBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('socialembed', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_url_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['url' => 'javascript:alert(1)'])->fails());
    }

    public function test_valid_platform_passes(): void
    {
        $this->assertTrue($this->validate(['platform' => 'auto'])->passes());
    }

    public function test_invalid_platform_fails(): void
    {
        $this->assertTrue($this->validate(['platform' => '__invalid__'])->fails());
    }
}
