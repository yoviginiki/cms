// Spotify link → embed player URL. Mirrors app/Support/Blocks/SpotifyEmbed.php
// — keep both in sync.

export const SPOTIFY_TYPES = ['playlist', 'album', 'track', 'artist', 'show', 'episode'] as const;

const T = SPOTIFY_TYPES.join('|');
const URI_RE = new RegExp(`^spotify:(${T}):([A-Za-z0-9]{10,40})$`);
const URL_RE = new RegExp(`https?://open\\.spotify\\.com/(?:intl-[a-z]{2}(?:-[a-z]{2})?/)?(?:embed/)?(${T})/([A-Za-z0-9]{10,40})`, 'i');

/** Accepts a share link, a spotify: URI, an embed URL or the whole <iframe> embed code. */
export function parseSpotify(input: string | undefined | null): { type: string; id: string } | null {
  const s = (input ?? '').trim();
  if (!s) return null;
  const m = s.match(URI_RE) ?? s.match(URL_RE);
  return m ? { type: m[1].toLowerCase(), id: m[2] } : null;
}

export function spotifyEmbedUrl(input: string | undefined | null, dark = false): string | null {
  const p = parseSpotify(input);
  return p ? `https://open.spotify.com/embed/${p.type}/${p.id}?utm_source=generator${dark ? '&theme=0' : ''}` : null;
}

/** Spotify's own default heights: lists get the tall player, single items the short one. */
export function spotifyDefaultHeight(input: string | undefined | null, size = 'normal'): number {
  if (size === 'compact') return 152;
  const p = parseSpotify(input);
  return p && (p.type === 'track' || p.type === 'episode') ? 152 : 352;
}
