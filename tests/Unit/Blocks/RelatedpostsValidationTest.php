<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\RelatedpostsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RelatedpostsValidationTest extends TestCase
{
    private RelatedpostsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new RelatedpostsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('relatedposts', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_limit_in_range(): void
    {
        $this->assertTrue($this->validate(['limit' => 1])->passes());
        $this->assertTrue($this->validate(['limit' => 20])->passes());
    }

    public function test_limit_out_of_range(): void
    {
        $this->assertTrue($this->validate(['limit' => 1 - 1])->fails());
        $this->assertTrue($this->validate(['limit' => 20 + 1])->fails());
    }

    public function test_valid_basedOn_passes(): void
    {
        $this->assertTrue($this->validate(['basedOn' => 'category'])->passes());
    }

    public function test_invalid_basedOn_fails(): void
    {
        $this->assertTrue($this->validate(['basedOn' => '__invalid__'])->fails());
    }
}
