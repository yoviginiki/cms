import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { safeDim } from '@/lib/blockStyles';
import { LAYOUT_GRID, LAYOUT_COLUMN_COUNT, type RowLayout } from './definition';

export const RowPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;

  const layout = (data.layout as RowLayout) || '1/2+1/2';
  const gap = safeDim(data.gap) || '16px';
  const maxWidth = safeDim(data.max_width) || undefined;
  const verticalAlign = (data.vertical_align as string) || 'stretch';

  const gridTemplate = LAYOUT_GRID[layout] || LAYOUT_GRID['1/2+1/2'];
  const colCount = LAYOUT_COLUMN_COUNT[layout] || 2;

  const style: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: gridTemplate,
    gap,
    alignItems: verticalAlign,
    maxWidth,
    margin: maxWidth ? '0 auto' : undefined,
  };

  // Show empty column placeholders when no children
  if (block.children.length === 0) {
    return (
      <div
        className="rounded border border-dashed border-green-300/50 p-1"
        style={style}
      >
        {Array.from({ length: colCount }, (_, i) => (
          <div
            key={i}
            className="border border-dashed border-green-300/30 rounded min-h-[60px] flex items-center justify-center"
          >
            <span className="text-xs text-green-400/60 uppercase tracking-wide">
              Col {i + 1}
            </span>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div
      className="rounded border border-dashed border-green-300/50 min-h-[40px]"
      style={style}
    />
  );
};
