import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const VideoPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    url: string;
    autoplay: boolean;
    muted: boolean;
    poster: string;
  };

  return (
    <div className="rounded border border-gray-200 bg-gray-900 p-6 text-center">
      <div className="text-4xl mb-2">▶</div>
      <div className="text-sm text-gray-300">
        {data.url ? (
          <span className="break-all">{data.url}</span>
        ) : (
          <span className="italic text-gray-500">No video URL set</span>
        )}
      </div>
      <div className="mt-2 flex items-center justify-center gap-3 text-xs text-gray-500">
        {data.autoplay && <span>Autoplay</span>}
        {data.muted && <span>Muted</span>}
        {data.poster && <span>Has poster</span>}
      </div>
    </div>
  );
};
