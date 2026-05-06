import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const LatestpostsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    limit: number; columns: number; layout: string;
    showImage: boolean; showExcerpt: boolean; showDate: boolean; showCategory: boolean;
    categoryId: string;
  };
  const limit = data.limit || 5;
  const columns = data.columns || 1;
  const layout = data.layout || 'cards';

  if (layout === 'compact') {
    return (
      <div className="rounded border border-gray-200 p-3">
        <div className="text-[10px] uppercase text-gray-400 mb-2">Blog Posts — compact</div>
        <div className="space-y-1">
          {Array.from({ length: Math.min(limit, 5) }).map((_, i) => (
            <div key={i} className="flex items-center gap-2 py-1 border-b border-gray-50">
              <div className="h-2.5 bg-gray-200 rounded flex-1" />
              {data.showDate !== false && <div className="h-2 bg-gray-100 rounded w-12" />}
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (layout === 'list') {
    return (
      <div className="rounded border border-gray-200 p-3">
        <div className="text-[10px] uppercase text-gray-400 mb-2">Blog Posts — list</div>
        <div className="space-y-2">
          {Array.from({ length: Math.min(limit, 4) }).map((_, i) => (
            <div key={i} className="flex items-center gap-3 pb-2 border-b border-gray-100">
              {data.showImage !== false && <div className="w-14 h-14 bg-gray-100 rounded flex-shrink-0" />}
              <div className="flex-1">
                {data.showCategory !== false && <div className="h-2 bg-blue-100 rounded w-12 mb-1" />}
                <div className="h-3 bg-gray-200 rounded w-3/4 mb-1" />
                {data.showExcerpt !== false && <div className="h-2 bg-gray-100 rounded w-full" />}
                {data.showDate !== false && <div className="h-2 bg-gray-50 rounded w-16 mt-1" />}
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  // Cards & Featured
  const isFeatured = layout === 'featured' && limit > 1;

  return (
    <div className="rounded border border-gray-200 p-3">
      <div className="text-[10px] uppercase text-gray-400 mb-2">
        Blog Posts — {columns} col{columns > 1 ? 's' : ''} × {limit}
        {data.categoryId && <span className="ml-1 text-blue-400">(filtered)</span>}
      </div>
      {isFeatured && (
        <div className="mb-2 rounded-lg border border-gray-200 overflow-hidden">
          {data.showImage !== false && <div className="h-24 bg-gray-100" />}
          <div className="p-2">
            {data.showCategory !== false && <div className="h-2 bg-blue-100 rounded w-14 mb-1" />}
            <div className="h-4 bg-gray-200 rounded w-2/3 mb-1" />
            {data.showExcerpt !== false && <div className="h-2 bg-gray-100 rounded w-full" />}
          </div>
        </div>
      )}
      <div className={`grid gap-2`} style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
        {Array.from({ length: Math.min(isFeatured ? limit - 1 : limit, 8) }).map((_, i) => (
          <div key={i} className="rounded-lg border border-gray-200 overflow-hidden">
            {data.showImage !== false && <div className="h-14 bg-gray-100" />}
            <div className="p-2">
              {data.showCategory !== false && <div className="h-1.5 bg-blue-50 rounded w-10 mb-1" />}
              <div className="h-2.5 bg-gray-200 rounded w-3/4 mb-1" />
              {data.showExcerpt !== false && <div className="h-2 bg-gray-100 rounded w-full" />}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
