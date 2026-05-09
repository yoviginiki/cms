<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\HeroBlockDefinition;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HeroValidationTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = (new HeroBlockDefinition())->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make(array_merge(['title' => 'Test'], $data), $this->rules);
    }

    // ── headlineTag ──────────────────────────────────────────────

    #[DataProvider('validHeadlineTagProvider')]
    public function test_valid_headlineTag_passes(string $tag): void
    {
        $this->assertTrue($this->validate(['headlineTag' => $tag])->passes());
    }

    public static function validHeadlineTagProvider(): array
    {
        return [['h1'], ['h2'], ['h3']];
    }

    #[DataProvider('invalidHeadlineTagProvider')]
    public function test_invalid_headlineTag_fails(string $tag): void
    {
        $this->assertTrue($this->validate(['headlineTag' => $tag])->fails());
    }

    public static function invalidHeadlineTagProvider(): array
    {
        return [['h4'], ['div'], ['script']];
    }

    // ── textAlignment ────────────────────────────────────────────

    #[DataProvider('validTextAlignmentProvider')]
    public function test_valid_textAlignment_passes(string $val): void
    {
        $this->assertTrue($this->validate(['textAlignment' => $val])->passes());
    }

    public static function validTextAlignmentProvider(): array
    {
        return [['left'], ['center'], ['right']];
    }

    public function test_invalid_textAlignment_fails(): void
    {
        $this->assertTrue($this->validate(['textAlignment' => 'justify'])->fails());
    }

    // ── sectionHeight ────────────────────────────────────────────

    #[DataProvider('validSectionHeightProvider')]
    public function test_valid_sectionHeight_passes(string $val): void
    {
        $this->assertTrue($this->validate(['sectionHeight' => $val])->passes());
    }

    public static function validSectionHeightProvider(): array
    {
        return [['auto'], ['sm'], ['md'], ['lg'], ['fullscreen']];
    }

    public function test_invalid_sectionHeight_fails(): void
    {
        $this->assertTrue($this->validate(['sectionHeight' => 'xl'])->fails());
    }

    // ── contentMaxWidth ──────────────────────────────────────────

    #[DataProvider('validContentMaxWidthProvider')]
    public function test_valid_contentMaxWidth_passes(string $val): void
    {
        $this->assertTrue($this->validate(['contentMaxWidth' => $val])->passes());
    }

    public static function validContentMaxWidthProvider(): array
    {
        return [['800px'], ['60rem'], ['100%']];
    }

    #[DataProvider('invalidContentMaxWidthProvider')]
    public function test_invalid_contentMaxWidth_fails(string $val): void
    {
        $this->assertTrue($this->validate(['contentMaxWidth' => $val])->fails());
    }

    public static function invalidContentMaxWidthProvider(): array
    {
        return [['javascript:'], ['800'], ['auto']];
    }

    // ── headlineColor ────────────────────────────────────────────

    #[DataProvider('validHeadlineColorProvider')]
    public function test_valid_headlineColor_passes(string $val): void
    {
        $this->assertTrue($this->validate(['headlineColor' => $val])->passes());
    }

    public static function validHeadlineColorProvider(): array
    {
        return [['#fff'], ['#1e40af'], ['rgba(0,0,0,0.5)']];
    }

    #[DataProvider('invalidHeadlineColorProvider')]
    public function test_invalid_headlineColor_fails(string $val): void
    {
        $this->assertTrue($this->validate(['headlineColor' => $val])->fails());
    }

    public static function invalidHeadlineColorProvider(): array
    {
        return [['<script>'], ['expression()']];
    }

    // ── headlineWeight ───────────────────────────────────────────

    #[DataProvider('validHeadlineWeightProvider')]
    public function test_valid_headlineWeight_passes(string $val): void
    {
        $this->assertTrue($this->validate(['headlineWeight' => $val])->passes());
    }

    public static function validHeadlineWeightProvider(): array
    {
        return [['400'], ['700']];
    }

    #[DataProvider('invalidHeadlineWeightProvider')]
    public function test_invalid_headlineWeight_fails(string $val): void
    {
        $this->assertTrue($this->validate(['headlineWeight' => $val])->fails());
    }

    public static function invalidHeadlineWeightProvider(): array
    {
        return [['350'], ['bold']];
    }

    // ── adaptiveTextColor ────────────────────────────────────────

    public function test_adaptiveTextColor_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['adaptiveTextColor' => true])->passes());
        $this->assertTrue($this->validate(['adaptiveTextColor' => false])->passes());
    }

    // ── mediaLoading ─────────────────────────────────────────────

    public function test_mediaLoading_accepts_eager(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'eager'])->passes());
    }

    public function test_mediaLoading_accepts_lazy(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'lazy'])->passes());
    }

    public function test_mediaLoading_rejects_invalid(): void
    {
        $this->assertTrue($this->validate(['mediaLoading' => 'auto'])->fails());
    }
}
