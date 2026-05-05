import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const RunningtextPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { content, columns, columnGap, columnRule } = block.data as {
    content: string;
    columns: number;
    columnGap: string;
    columnRule: boolean;
  };

  if (!content) {
    return (
      <div className="text-base-content/40 italic py-4">
        Click to add running text content...
      </div>
    );
  }

  return (
    <div
      className="prose max-w-none text-base-content/80"
      style={{
        columnCount: columns || 2,
        columnGap: columnGap || '40px',
        columnRule: columnRule ? '1px solid oklch(var(--bc) / 0.2)' : 'none',
      }}
      dangerouslySetInnerHTML={{ __html: content }}
    />
  );
};
