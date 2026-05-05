import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const sizeMap: Record<string, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
};

export const ModalPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    triggerText: string;
    title: string;
    size: string;
  };

  const sizeClass = sizeMap[data.size] || sizeMap.md;

  return (
    <div className="py-2">
      <div className="flex items-start gap-3">
        <span className="inline-block px-4 py-1.5 bg-blue-600 text-white rounded text-sm font-medium cursor-pointer">
          {data.triggerText || 'Open'}
        </span>
        <div className={`flex-1 ${sizeClass} border-2 border-dashed border-gray-300 rounded-lg p-3`}>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-gray-500">
              {data.title || 'Modal'}
            </span>
            <span className="text-gray-400 text-xs">&times;</span>
          </div>
          <div className="text-xs text-gray-400 text-center py-2">
            Modal content (children)
          </div>
        </div>
      </div>
    </div>
  );
};
