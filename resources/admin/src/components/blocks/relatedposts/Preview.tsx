import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const RelatedpostsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { limit: number; basedOn: string };
  const limit = data.limit || 3;

  return (
    <div className="grid grid-cols-3 gap-3">
      {Array.from({ length: limit }).map((_, i) => (
        <div key={i} className="rounded-lg border border-gray-200 overflow-hidden">
          <div className="h-20 bg-gray-100" />
          <div className="p-3">
            <div className="h-3 bg-gray-200 rounded w-3/4 mb-1" />
            <div className="h-2 bg-gray-100 rounded w-1/2" />
          </div>
        </div>
      ))}
    </div>
  );
};
