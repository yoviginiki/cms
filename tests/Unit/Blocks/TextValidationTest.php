<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\TextBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TextValidationTest extends TestCase
{
    private TextBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new TextBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('text', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertFalse($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    // ── Text alignment ──

    public function test_valid_text_align(): void
    {
        $this->assertTrue($this->validate(['textAlign' => 'center'])->passes());
        $this->assertTrue($this->validate(['textAlign' => 'justify'])->passes());
    }

    public function test_invalid_text_align_fails(): void
    {
        $this->assertTrue($this->validate(['textAlign' => 'invalid'])->fails());
    }

    // ── Text color ──

    public function test_valid_text_color(): void
    {
        $this->assertTrue($this->validate(['textColor' => '#ff0000'])->passes());
    }

    public function test_invalid_text_color_fails(): void
    {
        $this->assertTrue($this->validate(['textColor' => 'url(evil)'])->fails());
    }

    // ── Typography fields ──

    public function test_valid_font_size(): void
    {
        $this->assertTrue($this->validate(['fontSize' => '1.5rem'])->passes());
        $this->assertTrue($this->validate(['fontSize' => '18px'])->passes());
    }

    public function test_valid_font_weight(): void
    {
        $this->assertTrue($this->validate(['fontWeight' => '700'])->passes());
        $this->assertTrue($this->validate(['fontWeight' => ''])->passes());
    }

    public function test_invalid_font_weight_fails(): void
    {
        $this->assertTrue($this->validate(['fontWeight' => '999'])->fails());
    }

    public function test_valid_font_style(): void
    {
        $this->assertTrue($this->validate(['fontStyle' => 'italic'])->passes());
        $this->assertTrue($this->validate(['fontStyle' => ''])->passes());
    }

    public function test_invalid_font_style_fails(): void
    {
        $this->assertTrue($this->validate(['fontStyle' => 'oblique'])->fails());
    }

    public function test_valid_line_height(): void
    {
        $this->assertTrue($this->validate(['lineHeight' => '1.6'])->passes());
        $this->assertTrue($this->validate(['lineHeight' => '24px'])->passes());
    }

    public function test_valid_letter_spacing(): void
    {
        $this->assertTrue($this->validate(['letterSpacing' => '0.02em'])->passes());
    }

    // ── Full typography preserved with content ──

    public function test_all_typography_with_content_passes(): void
    {
        $this->assertTrue($this->validate([
            'content' => '<p>Hello <em>world</em></p>',
            'textAlign' => 'center',
            'textColor' => '#333',
            'fontSize' => '1.125rem',
            'fontWeight' => '500',
            'fontStyle' => 'italic',
            'lineHeight' => '1.6',
            'letterSpacing' => '0.02em',
        ])->passes());
    }
}
