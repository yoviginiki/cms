import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const GroupPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { tag: string };

  return (
    <div className="border border-dashed border-gray-200 rounded-lg p-3 min-h-[50px]">
      <div className="text-[10px] text-gray-300 uppercase tracking-wide mb-1">
        Group &lt;{data.tag || 'div'}&gt;
      </div>
      <div className="min-h-[30px] text-sm text-gray-400 italic">
        Child blocks render here
      </div>
    </div>
  );
};
