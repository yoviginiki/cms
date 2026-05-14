<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\PaywallBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PaywallValidationTest extends TestCase
{
    private PaywallBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new PaywallBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('paywall', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_previewLines_in_range(): void
    {
        $this->assertTrue($this->validate(['previewLines' => 0])->passes());
        $this->assertTrue($this->validate(['previewLines' => 20])->passes());
    }

    public function test_previewLines_out_of_range(): void
    {
        $this->assertTrue($this->validate(['previewLines' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['previewLines' => 20 + 1])->fails());
    }

    public function test_blurIntensity_in_range(): void
    {
        $this->assertTrue($this->validate(['blurIntensity' => 0])->passes());
        $this->assertTrue($this->validate(['blurIntensity' => 20])->passes());
    }

    public function test_blurIntensity_out_of_range(): void
    {
        $this->assertTrue($this->validate(['blurIntensity' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['blurIntensity' => 20 + 1])->fails());
    }

    public function test_heading_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['heading' => str_repeat('a', 255 + 1)])->fails());
    }

    public function test_ctaText_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['ctaText' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_ctaUrl_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['ctaUrl' => 'javascript:alert(1)'])->fails());
    }
}
