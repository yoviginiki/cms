import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const BreadcrumbsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { separator: string; showHome: boolean; homeLabel: string };
  const sep = data.separator || '/';

  return (
    <div className="rounded border border-gray-200 p-3">
      <div className="flex items-center gap-1.5 text-xs text-gray-400">
        {data.showHome !== false && <span className="text-blue-500 underline">{data.homeLabel || 'Home'}</span>}
        <span>{sep}</span>
        <span className="text-blue-500 underline">Parent Page</span>
        <span>{sep}</span>
        <span className="text-gray-600">Current Page</span>
      </div>
    </div>
  );
};
