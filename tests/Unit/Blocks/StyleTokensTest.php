<?php

namespace Tests\Unit\Blocks;

use App\Support\Blocks\BlockStyle;
use App\Support\Blocks\StyleTokens;
use PHPUnit\Framework\TestCase;

/**
 * P3 token-reference compilation: $a.b.c → var(--a-b-c), and the result must
 * survive BlockStyle's sanitizers (so tokens reach published CSS).
 */
class StyleTokensTest extends TestCase
{
    public function test_compiles_token_paths_to_css_vars(): void
    {
        $this->assertSame('var(--color-accent)', StyleTokens::compile('$color.accent'));
        $this->assertSame('var(--space-6)', StyleTokens::compile('$space.6'));
        $this->assertSame('var(--font-heading)', StyleTokens::compile('$font.heading'));
        $this->assertSame('var(--border-radius-md)', StyleTokens::compile('$border-radius.md'));
        $this->assertSame('var(--color-text-muted)', StyleTokens::compile('$color.text.muted'));
    }

    public function test_leaves_non_token_values_unchanged(): void
    {
        $this->assertSame('#ff0000', StyleTokens::compile('#ff0000'));
        $this->assertSame('16px', StyleTokens::compile('16px'));
        $this->assertSame('var(--color-accent)', StyleTokens::compile('var(--color-accent)'));
        $this->assertSame('', StyleTokens::compile(''));
        $this->assertSame(42, StyleTokens::compile(42));
    }

    public function test_invalid_token_paths_are_left_untouched(): void
    {
        // no dangerous injection survives (leading dot, spaces, parens)
        $this->assertSame('$.evil', StyleTokens::compile('$.evil'));
        $this->assertSame('$a b', StyleTokens::compile('$a b'));
        $this->assertSame('$x);color:red', StyleTokens::compile('$x);color:red'));
    }

    public function test_compiled_tokens_survive_the_sanitizers(): void
    {
        $this->assertSame('var(--color-accent)', BlockStyle::safeColor(StyleTokens::compile('$color.accent')));
        $this->assertSame('var(--space-6)', BlockStyle::safeDim(StyleTokens::compile('$space.6')));
        // an invalid/injection attempt compiles to itself then is dropped by the sanitizer
        $this->assertSame('', BlockStyle::safeColor(StyleTokens::compile('$x);color:red')));
    }

    public function test_compile_style_walks_nested_leaves(): void
    {
        $out = StyleTokens::compileStyle([
            'visual' => ['backgroundColor' => '$color.accent', 'opacity' => 0.5],
            'spacing' => ['paddingTop' => '$space.6', 'paddingBottom' => '16px'],
        ]);
        $this->assertSame('var(--color-accent)', $out['visual']['backgroundColor']);
        $this->assertSame(0.5, $out['visual']['opacity']);
        $this->assertSame('var(--space-6)', $out['spacing']['paddingTop']);
        $this->assertSame('16px', $out['spacing']['paddingBottom']);
    }
}
