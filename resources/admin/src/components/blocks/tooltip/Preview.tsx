import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const TooltipPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    triggerText: string;
    tooltipText: string;
    position: string;
  };

  return (
    <div className="py-2">
      <span className="inline-flex items-center gap-1 text-sm">
        <span className="border-b border-dashed border-gray-400 cursor-help">
          {data.triggerText || 'Hover me'}
        </span>
        <span className="text-[10px] bg-gray-800 text-white rounded px-1.5 py-0.5">
          {data.tooltipText || 'Tooltip content'}
        </span>
        <span className="text-[9px] text-gray-400 ml-1">({data.position || 'top'})</span>
      </span>
    </div>
  );
};
