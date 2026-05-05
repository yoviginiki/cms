<?php

if (!function_exists('theme')) {
    /**
     * Get a resolved theme token value by dot path.
     */
    function theme(string $path, mixed $default = null): mixed
    {
        return app(\App\Services\Theme\CurrentTheme::class)->get($path, $default);
    }
}

if (!function_exists('theme_var')) {
    /**
     * Get the CSS variable reference for a token path.
     * Returns something like "var(--semantic-color-brand)".
     */
    function theme_var(string $path): string
    {
        return 'var(--' . str_replace('.', '-', $path) . ')';
    }
}

if (!function_exists('theme_has')) {
    /**
     * Check if a token path exists in the current resolved theme.
     */
    function theme_has(string $path): bool
    {
        return app(\App\Services\Theme\CurrentTheme::class)->has($path);
    }
}
