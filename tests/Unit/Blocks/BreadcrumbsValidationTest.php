<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\BreadcrumbsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BreadcrumbsValidationTest extends TestCase
{
    private BreadcrumbsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new BreadcrumbsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('breadcrumbs', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_separator_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['separator' => str_repeat('a', 10 + 1)])->fails());
    }

    public function test_showHome_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showHome' => true])->passes());
        $this->assertTrue($this->validate(['showHome' => false])->passes());
    }

    public function test_homeLabel_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['homeLabel' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_showCurrent_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showCurrent' => true])->passes());
        $this->assertTrue($this->validate(['showCurrent' => false])->passes());
    }

    public function test_schema_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['schema' => true])->passes());
        $this->assertTrue($this->validate(['schema' => false])->passes());
    }
}
