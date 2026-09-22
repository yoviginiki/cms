<?php

namespace App\Domain\Publishing\Support;

/**
 * Single source of truth for what a redirect may look like (F04, audit
 * 2026-09-22). Used by the API validation AND by the publish-time writers,
 * so rows that pre-date validation (or arrive via CLI/import) are still
 * contained when files are generated.
 *
 * Literal sources are normalized path strings: leading '/', no empty/./..
 * segments, no encoded traversal, no control/whitespace characters.
 * Regex sources (is_regex) keep their metacharacters but obey the same
 * traversal/character rules — and never get an HTML stub.
 */
final class RedirectRules
{
    /** Validation rules for source_path; $isRegex switches the literal/regex kind. */
    public static function sourceRules(bool $required, bool $isRegex): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            'max:2048',
            function (string $attribute, mixed $value, \Closure $fail) use ($isRegex) {
                if (!is_string($value)) {
                    return $fail('The source path must be a string.');
                }
                $error = $isRegex ? self::regexSourceError($value) : self::literalSourceError($value);
                if ($error !== null) {
                    $fail($error);
                }
            },
        ];
    }

    /** Validation rules for target_url. */
    public static function targetRules(bool $required): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            'max:2048',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (!is_string($value) || !self::isSafeTarget($value)) {
                    $fail('The target must be a site-relative path starting with "/" or an http(s) URL, without whitespace or control characters.');
                }
            },
        ];
    }

    public static function literalSourceError(string $value): ?string
    {
        if (self::hasForbiddenChars($value)) {
            return 'The source path contains whitespace, control or backslash characters.';
        }
        if (preg_match('~%(2e|2f|5c)~i', $value)) {
            return 'The source path must not contain encoded path separators or dots.';
        }
        if (str_starts_with($value, '//')) {
            return 'The source path must be site-relative (a single leading "/").';
        }
        if (preg_match('~[\^$*+()\[\]{}|?]~', $value)) {
            return 'Regular-expression characters are only allowed for regex redirects (is_regex).';
        }
        if (self::normalizeLiteralSource($value) === null) {
            return 'The source path must be a normalized site path without "." or ".." segments.';
        }

        return null;
    }

    public static function regexSourceError(string $value): ?string
    {
        if (self::hasForbiddenChars($value)) {
            return 'The source pattern contains whitespace, control or backslash characters.';
        }
        if (preg_match('~%(2e|2f|5c)~i', $value) || preg_match('~(^|/)\.\.?(/|$)~', $value)) {
            return 'The source pattern must not contain traversal segments.';
        }
        if (trim($value, '/') === '') {
            return 'The source pattern is empty.';
        }
        if (@preg_match('~^' . $value . '$~', '') === false) {
            return 'The source pattern is not a valid regular expression.';
        }

        return null;
    }

    /**
     * Canonical literal source: '/a/b' (leading slash, no trailing slash,
     * no empty/dot segments). Null when the value cannot be normalized
     * safely — callers must then refuse it.
     */
    public static function normalizeLiteralSource(string $value): ?string
    {
        if (self::hasForbiddenChars($value) || preg_match('~%(2e|2f|5c)~i', $value)) {
            return null;
        }
        $segments = explode('/', $value);
        $clean = [];
        foreach ($segments as $i => $seg) {
            if ($seg === '') {
                if ($i === 0 || $i === count($segments) - 1) {
                    continue; // leading / trailing slash
                }

                return null; // empty inner segment ('a//b')
            }
            if ($seg === '.' || $seg === '..' || str_starts_with($seg, '..')) {
                return null;
            }
            $clean[] = $seg;
        }
        if ($clean === []) {
            return null;
        }

        return '/' . implode('/', $clean);
    }

    /**
     * Directory (relative to staging) an HTML stub for this source lands in,
     * or null when no stub must be written (regex sources, traversal, root).
     * Mirrors the historical "/old/?" tolerance: a trailing '/?' is a
     * literal-with-optional-slash and still gets a stub.
     */
    public static function stubRelativeDir(string $source): ?string
    {
        $source = preg_replace('~/\?$~', '', trim($source));
        if (preg_match('~[\^$*+()\[\]{}|?]~', $source)) {
            return null;
        }
        $normalized = self::normalizeLiteralSource($source);

        return $normalized === null ? null : ltrim($normalized, '/');
    }

    /**
     * Absolute stub file path inside $staging for a source, verified to
     * resolve within the staging tree — or null.
     */
    public static function stubPath(string $staging, string $source): ?string
    {
        $rel = self::stubRelativeDir($source);
        if ($rel === null) {
            return null;
        }
        $realStaging = realpath($staging);
        if ($realStaging === false) {
            return null;
        }
        $dir = $realStaging . '/' . $rel;
        // Every ancestor that already exists must resolve inside staging
        // (a symlinked directory could otherwise carry the write elsewhere).
        $probe = $dir;
        while (!file_exists($probe) && $probe !== $realStaging) {
            $probe = dirname($probe);
        }
        $realProbe = realpath($probe);
        if ($realProbe === false || !($realProbe === $realStaging || str_starts_with($realProbe, $realStaging . '/'))) {
            return null;
        }

        return $dir . '/index.html';
    }

    /** Site-relative path or http(s) URL, free of whitespace/control chars and script schemes. */
    public static function isSafeTarget(string $value): bool
    {
        if ($value === '' || self::hasForbiddenChars($value)) {
            return false;
        }
        if (str_starts_with($value, '/')) {
            return !str_starts_with($value, '//') && !str_starts_with($value, '/\\');
        }
        if (!preg_match('~^https?://~i', $value)) {
            return false;
        }
        $parts = parse_url($value);

        return is_array($parts) && !empty($parts['host']);
    }

    /** True when a redirect row is safe for the line-based outputs (_redirects / .htaccess). */
    public static function isSafeForLineOutput(string $source, string $target, bool $isRegex): bool
    {
        if (!self::isSafeTarget($target)) {
            return false;
        }

        return ($isRegex ? self::regexSourceError($source) : self::literalSourceError($source)) === null;
    }

    private static function hasForbiddenChars(string $value): bool
    {
        return $value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value) === 1;
    }
}
