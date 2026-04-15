import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const gapMap: Record<string, string> = {
  none: '0px',
  small: '8px',
  medium: '16px',
  large: '32px',
};

export const ColumnsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { columnCount, gap } = block.data as {
    columnCount: number;
    gap: string;
  };

  const columns = Array.from({ length: columnCount }, (_, i) => i);

  return (
    <div
      className="w-full"
      style={{
        display: 'grid',
        gridTemplateColumns: `repeat(${columnCount}, 1fr)`,
        gap: gapMap[gap] || gapMap.medium,
      }}
    >
      {columns.map((_, index) => (
        <div
          key={index}
          className="border-2 border-dashed border-gray-300 rounded-lg p-6 flex items-center justify-center min-h-[80px] bg-gray-50 text-gray-400 text-sm"
        >
          Column {index + 1}
        </div>
      ))}
    </div>
  );
};
