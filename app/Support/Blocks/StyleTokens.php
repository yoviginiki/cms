<?php

namespace App\Support\Blocks;

/**
 * Compiles design-token references in a style value to CSS custom properties
 * (Builder Experience P3). A style value written as `$a.b.c` targets the emitted
 * kebab variable `--a-b-c`, i.e. compiles to `var(--a-b-c)`:
 *
 *   $color.accent      → var(--color-accent)
 *   $space.6           → var(--space-6)
 *   $font.heading      → var(--font-heading)
 *   $border-radius.md  → var(--border-radius-md)
 *
 * The result is a plain `var(...)` string, which BlockStyle's safeColor/safeDim/
 * safeCssVal already validate and pass through — so tokens resolve at publish to
 * static CSS with zero runtime cost. Non-`$` values (raw colors, `var(...)`,
 * dimensions) are returned unchanged. Invalid `$` paths are returned unchanged
 * too and get dropped by the downstream sanitizer.
 */
class StyleTokens
{
    private const TOKEN_PATH = '/^[a-zA-Z][a-zA-Z0-9-]*(\.[a-zA-Z0-9-]+)*$/';

    public static function compile(mixed $value): mixed
    {
        if (!is_string($value) || $value === '' || $value[0] !== '$') {
            return $value;
        }
        $path = substr($value, 1);
        if (!preg_match(self::TOKEN_PATH, $path)) {
            return $value;
        }

        return 'var(--' . str_replace('.', '-', $path) . ')';
    }

    /** Recursively compile every string leaf of a style array. */
    public static function compileStyle(array $style): array
    {
        $out = [];
        foreach ($style as $key => $value) {
            if (is_array($value)) {
                $out[$key] = self::compileStyle($value);
            } elseif (is_string($value)) {
                $out[$key] = self::compile($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
