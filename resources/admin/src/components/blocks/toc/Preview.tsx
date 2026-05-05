import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const styleLabels: Record<string, string> = {
  inline: 'Inline',
  sidebar: 'Sidebar',
  numbered: 'Numbered',
};

export const TocPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    maxDepth: number;
    style: string;
    sticky: boolean;
  };

  return (
    <div className="rounded border border-gray-200 p-4">
      <div className="flex items-center justify-between mb-2">
        <span className="text-sm font-semibold text-gray-700">Table of Contents</span>
        <div className="flex gap-2">
          <span className="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">
            {styleLabels[data.style] || 'Inline'}
          </span>
          {data.sticky && (
            <span className="text-[10px] bg-blue-100 text-blue-600 rounded px-1.5 py-0.5">
              Sticky
            </span>
          )}
        </div>
      </div>
      <div className="space-y-1 text-xs text-gray-400">
        <div>H2 - Section heading</div>
        {(data.maxDepth || 3) >= 3 && <div className="ml-3">H3 - Subsection</div>}
        {(data.maxDepth || 3) >= 4 && <div className="ml-6">H4 - Sub-subsection</div>}
        <div>H2 - Another section</div>
      </div>
      <p className="text-[10px] text-gray-300 mt-2 italic">Auto-generated from page headings</p>
    </div>
  );
};
