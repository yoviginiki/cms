<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TabsBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TabsValidationTest extends TestCase
{
    private TabsBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TabsBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('tabs', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_tab_labels_accepts_array(): void
    {
        $this->assertTrue($this->validate(['tab_labels' => []])->passes());
    }

    public function test_tab_labels_rejects_string(): void
    {
        $this->assertTrue($this->validate(['tab_labels' => 'not-array'])->fails());
    }
}
