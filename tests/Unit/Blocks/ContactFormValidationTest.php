<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ContactFormBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContactFormValidationTest extends TestCase
{
    private ContactFormBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new ContactFormBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('contact-form', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_fields_accepts_array(): void
    {
        $this->assertTrue($this->validate(['fields' => []])->passes());
    }

    public function test_fields_rejects_string(): void
    {
        $this->assertTrue($this->validate(['fields' => 'not-array'])->fails());
    }

    public function test_submit_label_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['submit_label' => str_repeat('a', 100 + 1)])->fails());
    }
}
