<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\AuthorboxBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthorboxValidationTest extends TestCase
{
    private AuthorboxBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new AuthorboxBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('authorbox', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_showAvatar_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showAvatar' => true])->passes());
        $this->assertTrue($this->validate(['showAvatar' => false])->passes());
    }

    public function test_showBio_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showBio' => true])->passes());
        $this->assertTrue($this->validate(['showBio' => false])->passes());
    }

    public function test_showSocialLinks_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['showSocialLinks' => true])->passes());
        $this->assertTrue($this->validate(['showSocialLinks' => false])->passes());
    }

    public function test_valid_layout_passes(): void
    {
        $this->assertTrue($this->validate(['layout' => 'horizontal'])->passes());
    }

    public function test_invalid_layout_fails(): void
    {
        $this->assertTrue($this->validate(['layout' => '__invalid__'])->fails());
    }
}
