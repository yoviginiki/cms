import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const MenuPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { style: string; sticky: boolean; showLogo: boolean };

  return (
    <div className="rounded border border-gray-200 p-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          {data.showLogo && <div className="w-6 h-6 rounded bg-gray-200" />}
          <div className="flex gap-4 text-xs text-gray-500">
            <span>Home</span>
            <span>About</span>
            <span>Blog</span>
            <span>Contact</span>
          </div>
        </div>
        <div className="flex gap-1">
          <span className="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">{data.style}</span>
          {data.sticky && <span className="text-[10px] bg-blue-100 text-blue-600 rounded px-1.5 py-0.5">Sticky</span>}
        </div>
      </div>
    </div>
  );
};
