import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const TabsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { tab_labels: string[] };
  const labels = data.tab_labels || ['Tab 1', 'Tab 2'];

  return (
    <div className="rounded border border-gray-200">
      <div className="flex border-b border-gray-200">
        {labels.map((label, index) => (
          <div
            key={index}
            className={`px-4 py-2 text-sm font-medium ${
              index === 0
                ? 'text-blue-600 border-b-2 border-blue-600 bg-white'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            {label}
          </div>
        ))}
      </div>
      <div className="p-4 text-sm text-gray-400 italic">
        Tab content (child blocks) renders here
      </div>
    </div>
  );
};
