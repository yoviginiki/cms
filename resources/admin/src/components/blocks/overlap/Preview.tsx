import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const OverlapPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    offsetY: string;
    offsetX: string;
    zIndex: number;
  };

  return (
    <div className="border-2 border-dashed border-purple-300 rounded-lg p-4 min-h-[60px] bg-purple-50 relative">
      <div className="text-xs text-purple-400 uppercase tracking-wide mb-2">
        Overlap (Y: {data.offsetY}, X: {data.offsetX}, z: {data.zIndex})
      </div>
      <div className="absolute -top-2 -left-2 w-4 h-4 border-2 border-purple-400 bg-white rounded-full" title="Offset indicator" />
      <div className="min-h-[40px] text-sm text-gray-500 italic">
        Child blocks render here
      </div>
    </div>
  );
};
