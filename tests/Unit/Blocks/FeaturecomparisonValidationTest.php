<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\FeaturecomparisonBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FeaturecomparisonValidationTest extends TestCase
{
    private FeaturecomparisonBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new FeaturecomparisonBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('featurecomparison', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_plans_accepts_array(): void
    {
        $this->assertTrue($this->validate(['plans' => []])->passes());
    }

    public function test_plans_rejects_string(): void
    {
        $this->assertTrue($this->validate(['plans' => 'not-array'])->fails());
    }

    public function test_features_accepts_array(): void
    {
        $this->assertTrue($this->validate(['features' => []])->passes());
    }

    public function test_features_rejects_string(): void
    {
        $this->assertTrue($this->validate(['features' => 'not-array'])->fails());
    }
}
