import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { spotifyEmbedUrl, spotifyDefaultHeight } from '@/lib/spotify';

/** The real Spotify player (non-interactive while editing), sized like the published block. */
export const SpotifyPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { url?: string; theme?: string; size?: string; height?: number };
  const src = spotifyEmbedUrl(data.url, data.theme === 'dark');
  const height = data.height || spotifyDefaultHeight(data.url, data.size);

  if (!src) {
    return (
      <div className="w-full h-full min-h-[80px] rounded-xl border-2 border-dashed border-[#1DB954]/50 bg-[#121212] flex flex-col items-center justify-center gap-1 text-gray-300 p-4 text-center">
        <div className="text-2xl text-[#1DB954]">♫</div>
        <span className="text-xs">{data.url ? 'Not a Spotify link' : 'Paste a Spotify link in the panel'}</span>
      </div>
    );
  }

  return (
    <iframe src={src} title="Spotify" className="w-full block pointer-events-none" style={{ height, border: 0, borderRadius: 12 }}
      allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" />
  );
};
