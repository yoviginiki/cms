import { describe, it, expect } from 'vitest';
import { parseSpotify, spotifyEmbedUrl, spotifyDefaultHeight } from './spotify';

describe('spotify link parsing', () => {
  it('reads a share link with ?si= and an intl prefix', () => {
    expect(parseSpotify('https://open.spotify.com/intl-de/playlist/37i9dQZF1DXcBWIGoYBM5M?si=abc123'))
      .toEqual({ type: 'playlist', id: '37i9dQZF1DXcBWIGoYBM5M' });
  });
  it('reads a spotify: URI and pasted iframe code', () => {
    expect(parseSpotify('spotify:album:4aawyAB9vmqN3uQ7FjRGTy')).toEqual({ type: 'album', id: '4aawyAB9vmqN3uQ7FjRGTy' });
    expect(parseSpotify('<iframe src="https://open.spotify.com/embed/track/11dFghVXANMlKmJXsNCbNl?utm_source=generator" width="100%"></iframe>'))
      .toEqual({ type: 'track', id: '11dFghVXANMlKmJXsNCbNl' });
  });
  it('rejects non-Spotify input', () => {
    expect(parseSpotify('https://evil.example/playlist/37i9dQZF1DXcBWIGoYBM5M')).toBeNull();
    expect(spotifyEmbedUrl('javascript:alert(1)')).toBeNull();
  });
  it('builds the embed URL and picks the default height', () => {
    expect(spotifyEmbedUrl('spotify:playlist:37i9dQZF1DXcBWIGoYBM5M', true))
      .toBe('https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M?utm_source=generator&theme=0');
    expect(spotifyDefaultHeight('spotify:track:11dFghVXANMlKmJXsNCbNl')).toBe(152);
    expect(spotifyDefaultHeight('spotify:playlist:37i9dQZF1DXcBWIGoYBM5M')).toBe(352);
  });
});
