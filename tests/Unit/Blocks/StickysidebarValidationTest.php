<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\StickysidebarBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StickysidebarValidationTest extends TestCase
{
    private StickysidebarBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new StickysidebarBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('stickysidebar', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_sidebarSide_passes(): void
    {
        $this->assertTrue($this->validate(['sidebarSide' => 'left'])->passes());
    }

    public function test_invalid_sidebarSide_fails(): void
    {
        $this->assertTrue($this->validate(['sidebarSide' => '__invalid__'])->fails());
    }

    public function test_sidebarWidth_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['sidebarWidth' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_gap_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['gap' => str_repeat('a', 20 + 1)])->fails());
    }

    public function test_stickyOffset_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['stickyOffset' => str_repeat('a', 20 + 1)])->fails());
    }
}
