<?php

namespace App\Support\Blocks;

/**
 * YouTube link → embed player URL. Understands single videos (watch?v=,
 * youtu.be/, embed/, shorts/, live/, m./music. hosts) and playlists
 * (playlist?list=, or a video link carrying &list=). Mirrors
 * resources/admin/src/lib/youtube.ts — keep both in sync.
 */
class YouTubeEmbed
{
    /** @return array{video:?string,list:?string}|null */
    public static function parse(?string $input): ?array
    {
        $s = trim((string) $input);
        if ($s === '' || !preg_match('~^(?:https?://)?(?:(?:www|m|music)\.)?(?:youtube(?:-nocookie)?\.com|youtu\.be)/~i', $s)) {
            return null;
        }
        $video = null;
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{11})~', $s, $m)
            || preg_match('~/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~', $s, $m)
            || preg_match('~[?&]v=([A-Za-z0-9_-]{11})~', $s, $m)) {
            $video = $m[1];
        }
        $list = preg_match('~[?&]list=([A-Za-z0-9_-]{10,64})~', $s, $m) ? $m[1] : null;

        return $video || $list ? ['video' => $video, 'list' => $list] : null;
    }

    /** Embed URL (youtube-nocookie), or null when the input is not a YouTube link. */
    public static function embedUrl(?string $input, bool $autoplay = false, bool $muted = false): ?string
    {
        $p = self::parse($input);
        if (!$p) {
            return null;
        }
        $q = ['playsinline' => 1];
        if ($p['list']) {
            $q['list'] = $p['list'];
        }
        if ($autoplay) {
            $q['autoplay'] = 1;
        }
        if ($muted) {
            $q['mute'] = 1;
        }

        return 'https://www.youtube-nocookie.com/embed/' . ($p['video'] ?? 'videoseries') . '?' . http_build_query($q);
    }
}
