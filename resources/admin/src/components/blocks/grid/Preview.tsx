import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const GridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    templateColumns: string;
    templateRows: string;
    gap: string;
    autoFlow: string;
  };

  // Estimate column count from templateColumns for preview placeholders
  const colCount = (data.templateColumns || '1fr 1fr').trim().split(/\s+/).length;
  const cells = Array.from({ length: colCount }, (_, i) => i);

  return (
    <div
      className="w-full rounded border border-dashed border-gray-300 p-2"
      style={{
        display: 'grid',
        gridTemplateColumns: data.templateColumns || '1fr 1fr',
        gap: data.gap || '16px',
      }}
    >
      {cells.map((_, index) => (
        <div
          key={index}
          className="border border-dashed border-gray-300 rounded p-4 flex items-center justify-center min-h-[60px] bg-gray-50 text-gray-400 text-xs"
        >
          Cell {index + 1}
        </div>
      ))}
    </div>
  );
};
