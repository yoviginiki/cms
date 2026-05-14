<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\CtabannerBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CtabannerValidationTest extends TestCase
{
    private CtabannerBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new CtabannerBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('ctabanner', $this->def->type());
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

    public function test_valid_backgroundStyle_passes(): void
    {
        $this->assertTrue($this->validate(['backgroundStyle' => 'solid'])->passes());
    }

    public function test_invalid_backgroundStyle_fails(): void
    {
        $this->assertTrue($this->validate(['backgroundStyle' => '__invalid__'])->fails());
    }

    public function test_backgroundColor_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['backgroundColor' => str_repeat('a', 50 + 1)])->fails());
    }
}
