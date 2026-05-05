import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const LatestpostsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { limit: number; layout: string; showImage: boolean };
  const limit = data.limit || 5;
  const isCards = data.layout === 'cards';

  if (isCards) {
    return (
      <div className="grid grid-cols-3 gap-3">
        {Array.from({ length: Math.min(limit, 6) }).map((_, i) => (
          <div key={i} className="rounded-lg border border-gray-200 overflow-hidden">
            {data.showImage && <div className="h-16 bg-gray-100" />}
            <div className="p-2">
              <div className="h-3 bg-gray-200 rounded w-3/4 mb-1" />
              <div className="h-2 bg-gray-100 rounded w-1/2" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {Array.from({ length: limit }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 border-b border-gray-100 pb-2">
          {data.showImage && <div className="w-12 h-12 bg-gray-100 rounded flex-shrink-0" />}
          <div className="flex-1">
            <div className="h-3 bg-gray-200 rounded w-2/3 mb-1" />
            <div className="h-2 bg-gray-100 rounded w-1/3" />
          </div>
        </div>
      ))}
    </div>
  );
};
