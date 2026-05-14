<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\LatestpostsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LatestpostsValidationTest extends TestCase
{
    private LatestpostsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new LatestpostsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('latestposts', $this->def->type());
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

    public function test_valid_layout_passes(): void
    {
        $this->assertTrue($this->validate(['layout' => 'compact'])->passes());
    }

    public function test_invalid_layout_fails(): void
    {
        $this->assertTrue($this->validate(['layout' => '__invalid__'])->fails());
    }

    public function test_valid_orderBy_passes(): void
    {
        $this->assertTrue($this->validate(['orderBy' => 'latest'])->passes());
    }

    public function test_invalid_orderBy_fails(): void
    {
        $this->assertTrue($this->validate(['orderBy' => '__invalid__'])->fails());
    }

    public function test_showImage_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showImage' => true])->passes());
        $this->assertTrue($this->validate(['showImage' => false])->passes());
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

    public function test_showContent_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showContent' => true])->passes());
        $this->assertTrue($this->validate(['showContent' => false])->passes());
    }

    public function test_excerptLength_in_range(): void
    {
        $this->assertTrue($this->validate(['excerptLength' => 0])->passes());
        $this->assertTrue($this->validate(['excerptLength' => 500])->passes());
    }

    public function test_excerptLength_out_of_range(): void
    {
        $this->assertTrue($this->validate(['excerptLength' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['excerptLength' => 500 + 1])->fails());
    }
}
