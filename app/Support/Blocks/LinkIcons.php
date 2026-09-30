<?php

namespace App\Support\Blocks;

/**
 * Icons for the `link` block (24×24 stroke SVGs, currentColor) + URL helpers.
 * Mirrors resources/admin/src/components/blocks/link/icons.tsx — keep in sync.
 */
class LinkIcons
{
    public const SHAPES = [
        'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'external'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'arrow'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'music'     => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'play'      => '<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4z"/>',
        'spotify'   => '<circle cx="12" cy="12" r="10"/><path d="M7 9.5c3.5-1 7-.7 10 1"/><path d="M7.5 12.8c3-.8 6-.5 8.5.9"/><path d="M8 15.8c2.3-.6 4.6-.4 6.5.7"/>',
        'youtube'   => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
        'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'mail'      => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'file'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
    ];

    public const BRAND = ['spotify' => '#1DB954', 'youtube' => '#FF0000', 'instagram' => '#E1306C', 'facebook' => '#1877F2'];

    /** 'auto' → an icon guessed from the URL; otherwise the named icon (or none). */
    public static function resolve(string $icon, string $url): ?string
    {
        if ($icon === 'none') {
            return null;
        }
        if ($icon !== 'auto') {
            return isset(self::SHAPES[$icon]) ? $icon : 'link';
        }
        $u = strtolower($url);

        return match (true) {
            str_contains($u, 'spotify.com') || str_starts_with($u, 'spotify:') => 'spotify',
            str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be') => 'youtube',
            str_contains($u, 'instagram.com') => 'instagram',
            str_contains($u, 'facebook.com') => 'facebook',
            str_starts_with($u, 'mailto:') => 'mail',
            str_starts_with($u, 'tel:') => 'phone',
            (bool) preg_match('~\.(pdf|zip|docx?|xlsx?|pptx?)(\?|$)~', $u) => 'file',
            (bool) preg_match('~^https?://~', $u) => 'external',
            default => 'arrow',
        };
    }

    public static function svg(string $name): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1.15em" height="1.15em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0">'
            . (self::SHAPES[$name] ?? self::SHAPES['link']) . '</svg>';
    }

    /** Only http(s), mailto, tel, spotify:, site-relative paths and #anchors. */
    public static function safeHref(string $url): ?string
    {
        $u = trim($url);
        if ($u === '') {
            return null;
        }

        return preg_match('~^(https?://|mailto:|tel:|spotify:|/(?!/)|#|\?)~i', $u) ? $u : (preg_match('~^[a-z0-9-]+(\.[a-z0-9-]+)+(/|$)~i', $u) ? 'https://' . $u : null);
    }
}
