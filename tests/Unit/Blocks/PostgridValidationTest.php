<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PostgridBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PostgridValidationTest extends TestCase
{
    private PostgridBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PostgridBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('postgrid', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_categoryId_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['categoryId' => str_repeat('a', 36 + 1)])->fails());
    }

    public function test_limit_in_range(): void
    {
        $this->assertTrue($this->validate(['limit' => 1])->passes());
        $this->assertTrue($this->validate(['limit' => 50])->passes());
    }

    public function test_limit_out_of_range(): void
    {
        $this->assertTrue($this->validate(['limit' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['limit' => 50 + 1])->fails());
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

    public function test_valid_cardStyle_passes(): void
    {
        $this->assertTrue($this->validate(['cardStyle' => 'vertical'])->passes());
    }

    public function test_invalid_cardStyle_fails(): void
    {
        $this->assertTrue($this->validate(['cardStyle' => '__invalid__'])->fails());
    }

    public function test_showExcerpt_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showExcerpt' => true])->passes());
        $this->assertTrue($this->validate(['showExcerpt' => false])->passes());
    }
}
