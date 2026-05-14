<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\NewsletterBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NewsletterValidationTest extends TestCase
{
    private NewsletterBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new NewsletterBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('newsletter', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_heading_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['heading' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_buttonText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['buttonText' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_endpoint_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['endpoint' => 'javascript:alert(1)'])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'inline'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }
}
