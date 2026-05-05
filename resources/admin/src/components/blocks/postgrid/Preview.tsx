import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const PostgridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { categoryId: string; limit: number; columns: number; cardStyle: string; showExcerpt: boolean };
  const cols = data.columns || 3;
  const limit = data.limit || 6;

  return (
    <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
      {Array.from({ length: limit }).map((_, i) => (
        <div key={i} className="rounded-lg border border-gray-200 overflow-hidden">
          <div className="h-20 bg-gray-100" />
          <div className="p-3">
            <div className="h-3 bg-gray-200 rounded w-3/4 mb-2" />
            {data.showExcerpt && <div className="h-2 bg-gray-100 rounded w-full" />}
          </div>
        </div>
      ))}
    </div>
  );
};
