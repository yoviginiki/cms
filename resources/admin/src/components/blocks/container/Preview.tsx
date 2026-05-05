import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const ContainerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    maxWidth: string;
    centered: boolean;
  };

  return (
    <div
      className="border-2 border-dashed border-gray-300 rounded-lg p-4 min-h-[60px]"
      style={{
        maxWidth: `${data.maxWidth}px`,
        margin: data.centered ? '0 auto' : undefined,
      }}
    >
      <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">
        Container ({data.maxWidth}px{data.centered ? ', centered' : ''})
      </div>
      <div className="min-h-[40px] text-sm text-gray-500 italic">
        Child blocks render here
      </div>
    </div>
  );
};
