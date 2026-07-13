<?php

namespace App\Domain\Publishing\Services;

use App\Models\Site;

/**
 * Default favicon for published sites (fleet finding: no favicon was ever
 * published, so every page of every site logged a /favicon.ico 404 to the
 * console — a Lighthouse best-practices failure platform-wide).
 *
 * Generates a small SVG — the site's initial on the theme's primary color —
 * written to /favicon.svg at publish and linked from every head. A site can
 * override by uploading its own icon later; the <link rel="icon"> also stops
 * browsers from auto-requesting /favicon.ico.
 */
class FaviconGenerator
{
    public function generate(Site $site): string
    {
        $initial = mb_strtoupper(mb_substr(trim($site->name) ?: 'S', 0, 1));
        $tokens = $site->theme?->config['tokens'] ?? [];
        $bg = $this->safeColor($tokens['color-primary'] ?? $tokens['color-accent'] ?? '') ?: '#1a1a18';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" fill="' . $bg . '"/>'
            . '<text x="32" y="44" text-anchor="middle" font-family="system-ui,sans-serif" font-size="36" font-weight="600" fill="#ffffff">'
            . htmlspecialchars($initial, ENT_XML1)
            . '</text></svg>';
    }

    /** The head link every published page should carry. */
    public function headLink(): string
    {
        return '<link rel="icon" type="image/svg+xml" href="/favicon.svg">';
    }

    private function safeColor(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($value)) ? trim($value) : '';
    }
}
