import { describe, it, expect } from 'vitest';
import { parseYouTube, youTubeEmbedUrl } from './youtube';

describe('youtube link parsing', () => {
  it('reads single videos in every common form', () => {
    for (const u of ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?si=x', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://m.youtube.com/watch?feature=share&v=dQw4w9WgXcQ']) {
      expect(parseYouTube(u)).toEqual({ video: 'dQw4w9WgXcQ', list: null });
    }
  });
  it('reads playlists, alone or with a video', () => {
    expect(youTubeEmbedUrl('https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'))
      .toBe('https://www.youtube-nocookie.com/embed/videoseries?playsinline=1&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG');
    expect(youTubeEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&index=2', true))
      .toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?playsinline=1&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&autoplay=1');
  });
  it('ignores other hosts', () => {
    expect(parseYouTube('https://evil.example/watch?v=dQw4w9WgXcQ')).toBeNull();
    expect(parseYouTube('https://example.com/video.mp4')).toBeNull();
  });
});
