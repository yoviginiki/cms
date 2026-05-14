<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\BeforeafterBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BeforeafterValidationTest extends TestCase
{
    private BeforeafterBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new BeforeafterBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('beforeafter', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_beforeSrc_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['beforeSrc' => 'javascript:alert(1)'])->fails());
    }

    public function test_afterSrc_blocks_javascript_uri(): void
    {
        $this->assertTrue($this->validate(['afterSrc' => 'javascript:alert(1)'])->fails());
    }

    public function test_beforeLabel_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['beforeLabel' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_afterLabel_rejects_overlength(): void
    {
        $this->assertTrue($this->validate(['afterLabel' => str_repeat('a', 100 + 1)])->fails());
    }

    public function test_initialPosition_in_range(): void
    {
        $this->assertTrue($this->validate(['initialPosition' => 0])->passes());
        $this->assertTrue($this->validate(['initialPosition' => 100])->passes());
    }

    public function test_initialPosition_out_of_range(): void
    {
        $this->assertTrue($this->validate(['initialPosition' => 0 - 1])->fails());
        $this->assertTrue($this->validate(['initialPosition' => 100 + 1])->fails());
    }
}
