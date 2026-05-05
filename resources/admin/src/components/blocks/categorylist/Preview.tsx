import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const CategorylistPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { style: string; showCount: boolean; parentOnly: boolean };
  const placeholders = ['Technology', 'Design', 'Business', 'Marketing'];

  if (data.style === 'badges') {
    return (
      <div className="flex flex-wrap gap-2">
        {placeholders.map((cat, i) => (
          <span key={i} className="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 rounded-full text-xs">
            {cat}
            {data.showCount && <span className="text-gray-400">(5)</span>}
          </span>
        ))}
      </div>
    );
  }

  if (data.style === 'cards') {
    return (
      <div className="grid grid-cols-2 gap-3">
        {placeholders.map((cat, i) => (
          <div key={i} className="rounded-lg border border-gray-200 p-3 text-center">
            <div className="text-sm font-semibold">{cat}</div>
            {data.showCount && <div className="text-xs text-gray-400">5 posts</div>}
          </div>
        ))}
      </div>
    );
  }

  return (
    <ul className="space-y-1">
      {placeholders.map((cat, i) => (
        <li key={i} className="text-sm text-blue-600 flex justify-between">
          <span>{cat}</span>
          {data.showCount && <span className="text-gray-400">(5)</span>}
        </li>
      ))}
    </ul>
  );
};
