<?php

namespace App\Support\Blocks;

/**
 * Line icons for the social-links block (24×24, stroke = currentColor, the
 * Lucide style the admin uses). Keys are the block's `network` values; the
 * admin preview maps the same keys to lucide-react icons.
 */
final class SocialIcons
{
    public const NETWORKS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'github' => 'GitHub',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'contact' => 'Contact',
        'email' => 'Email',
        'phone' => 'Phone',
        'website' => 'Website',
    ];

    private const SHAPES = [
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
        'x' => '<path d="M4 4l16 16"/><path d="M20 4 4 20"/>',
        'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>',
        'youtube' => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        'tiktok' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
        'whatsapp' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'telegram' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'contact' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'email' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'website' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
    ];

    public static function svg(string $network, string $size): string
    {
        $shape = self::SHAPES[$network] ?? self::SHAPES['website'];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $shape . '</svg>';
    }

    /** Normalise what people type: bare emails/phones become mailto:/tel:, bare domains get https://. */
    public static function href(string $network, string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // A page of this site (/kontakti/) or an anchor stays as it is
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }
        if (str_starts_with($url, '#')) {
            return $url;
        }
        if ($network === 'contact') {
            if (filter_var($url, FILTER_VALIDATE_EMAIL)) {
                $network = 'email';
            } elseif (preg_match('/^\+?[\d\s().-]{6,}$/', $url)) {
                $network = 'phone';
            }
        }
        if ($network === 'email' && !str_starts_with($url, 'mailto:')) {
            $url = 'mailto:' . $url;
        } elseif ($network === 'phone' && !str_starts_with($url, 'tel:')) {
            $url = 'tel:' . preg_replace('/[^\d+]/', '', $url);
        } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            return null; // any other scheme (javascript:, data:, …) is refused
        } elseif (!preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return preg_match('#^(https?://|mailto:|tel:)#i', $url) ? $url : null;
    }
}
