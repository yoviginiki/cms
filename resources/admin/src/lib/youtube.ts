// YouTube link → embed player URL. Mirrors app/Support/Blocks/YouTubeEmbed.php
// — keep both in sync.

const HOST_RE = /^(?:https?:\/\/)?(?:(?:www|m|music)\.)?(?:youtube(?:-nocookie)?\.com|youtu\.be)\//i;

/** Single videos (watch?v=, youtu.be, embed/, shorts/, live/) and playlists (list=). */
export function parseYouTube(input: string | undefined | null): { video: string | null; list: string | null } | null {
  const s = (input ?? '').trim();
  if (!s || !HOST_RE.test(s)) return null;
  const video = (s.match(/youtu\.be\/([A-Za-z0-9_-]{11})/) ?? s.match(/\/(?:embed|shorts|live|v)\/([A-Za-z0-9_-]{11})/) ?? s.match(/[?&]v=([A-Za-z0-9_-]{11})/))?.[1] ?? null;
  const list = s.match(/[?&]list=([A-Za-z0-9_-]{10,64})/)?.[1] ?? null;
  return video || list ? { video, list } : null;
}

export function youTubeEmbedUrl(input: string | undefined | null, autoplay = false, muted = false): string | null {
  const p = parseYouTube(input);
  if (!p) return null;
  const q = new URLSearchParams({ playsinline: '1' });
  if (p.list) q.set('list', p.list);
  if (autoplay) q.set('autoplay', '1');
  if (muted) q.set('mute', '1');
  return `https://www.youtube-nocookie.com/embed/${p.video ?? 'videoseries'}?${q.toString()}`;
}
