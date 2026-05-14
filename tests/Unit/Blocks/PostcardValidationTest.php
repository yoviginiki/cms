<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PostcardBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PostcardValidationTest extends TestCase
{
    private PostcardBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PostcardBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('postcard', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_postId_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['postId' => str_repeat('a', 36 + 1)])->fails());
    }

    public function test_valid_style_passes(): void
    {
        $this->assertTrue($this->validate(['style' => 'vertical'])->passes());
    }

    public function test_invalid_style_fails(): void
    {
        $this->assertTrue($this->validate(['style' => '__invalid__'])->fails());
    }

    public function test_showExcerpt_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showExcerpt' => true])->passes());
        $this->assertTrue($this->validate(['showExcerpt' => false])->passes());
    }

    public function test_showDate_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showDate' => true])->passes());
        $this->assertTrue($this->validate(['showDate' => false])->passes());
    }

    public function test_showCategory_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showCategory' => true])->passes());
        $this->assertTrue($this->validate(['showCategory' => false])->passes());
    }
}
