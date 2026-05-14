<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\OverlapBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OverlapValidationTest extends TestCase
{
    private OverlapBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new OverlapBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('overlap', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_offsetY_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['offsetY' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_offsetX_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['offsetX' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_zIndex_in_range(): void
    {
        $this->assertTrue($this->validate(['zIndex' => 0])->passes());
        $this->assertTrue($this->validate(['zIndex' => 100])->passes());
    }

    public function test_zIndex_out_of_range(): void
    {
        $this->assertTrue($this->validate(['zIndex' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['zIndex' => 100 + 1])->fails());
    }
}
