<?php

namespace App\Support\Blocks;

/**
 * Turns whatever the user pasted from Spotify — a share link
 * (open.spotify.com/[intl-xx/]playlist/ID?si=…), a spotify:playlist:ID URI, an
 * embed URL or the whole <iframe> embed code — into the official embed player
 * URL. Anything else yields null, so only open.spotify.com is ever framed.
 * Mirrors resources/admin/src/lib/spotify.ts — keep both in sync.
 */
class SpotifyEmbed
{
    public const TYPES = ['playlist', 'album', 'track', 'artist', 'show', 'episode'];

    /** @return array{type:string,id:string}|null */
    public static function parse(?string $input): ?array
    {
        $s = trim((string) $input);
        if ($s === '') {
            return null;
        }
        $types = implode('|', self::TYPES);
        if (preg_match('~^spotify:(' . $types . '):([A-Za-z0-9]{10,40})$~', $s, $m)) {
            return ['type' => $m[1], 'id' => $m[2]];
        }
        if (preg_match('~https?://open\.spotify\.com/(?:intl-[a-z]{2}(?:-[a-z]{2})?/)?(?:embed/)?(' . $types . ')/([A-Za-z0-9]{10,40})~i', $s, $m)) {
            return ['type' => strtolower($m[1]), 'id' => $m[2]];
        }

        return null;
    }

    /** Embed player URL, or null when the input is not a Spotify link. */
    public static function embedUrl(?string $input, bool $dark = false): ?string
    {
        $p = self::parse($input);
        if (!$p) {
            return null;
        }

        return 'https://open.spotify.com/embed/' . $p['type'] . '/' . $p['id'] . '?utm_source=generator' . ($dark ? '&theme=0' : '');
    }

    /** Spotify's own default player heights: lists get the tall player, single items the short one. */
    public static function defaultHeight(?string $input, string $size = 'normal'): int
    {
        $p = self::parse($input);
        if ($size === 'compact') {
            return 152;
        }

        return $p && in_array($p['type'], ['track', 'episode'], true) ? 152 : 352;
    }
}
