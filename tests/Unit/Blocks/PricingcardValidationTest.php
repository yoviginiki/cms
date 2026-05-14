<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PricingcardBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PricingcardValidationTest extends TestCase
{
    private PricingcardBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PricingcardBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('pricingcard', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_planName_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['planName' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_price_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['price' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_period_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['period' => str_repeat('a', 50 + 1)])->fails());
    }

    public function test_features_accepts_array(): void
    {
        $this->assertTrue($this->validate(['features' => []])->passes());
    }

    public function test_features_rejects_string(): void
    {
        $this->assertTrue($this->validate(['features' => 'not-array'])->fails());
    }

    public function test_ctaText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['ctaText' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_highlighted_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['highlighted' => true])->passes());
        $this->assertTrue($this->validate(['highlighted' => false])->passes());
    }

    public function test_badge_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['badge' => str_repeat('a', 50 + 1)])->fails());
    }
}
