import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const AudioPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { url, title, artist } = block.data as {
    url: string;
    title: string;
    artist: string;
  };

  if (!url) {
    return (
      <div className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-8 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <svg
            className="mx-auto h-10 w-10 mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"
            />
          </svg>
          <p className="text-sm">No audio file</p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-base-200 rounded-lg p-4">
      <div className="flex items-center gap-3 mb-2">
        <div className="bg-primary/10 rounded-full p-2">
          <svg className="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"
            />
          </svg>
        </div>
        <div>
          {title && <p className="text-sm font-medium">{title}</p>}
          {artist && <p className="text-xs text-gray-500">{artist}</p>}
        </div>
      </div>
      <div className="w-full bg-gray-300 rounded-full h-1.5">
        <div className="bg-primary h-1.5 rounded-full" style={{ width: '35%' }} />
      </div>
    </div>
  );
};
