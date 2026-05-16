import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { safeDim, safeColor } from '@/lib/blockStyles';

export const ColumnPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;

  const VALID_ALIGNS = ['start', 'center', 'end', 'stretch'];
  const rawAlign = (data.vertical_align as string) || 'start';
  const verticalAlign = VALID_ALIGNS.includes(rawAlign) ? rawAlign : 'start';

  const style: React.CSSProperties = {
    padding: safeDim(data.padding) || undefined,
    display: 'flex',
    flexDirection: 'column',
    justifyContent: verticalAlign === 'center' ? 'center'
      : verticalAlign === 'end' ? 'flex-end'
      : verticalAlign === 'stretch' ? 'stretch'
      : 'flex-start',
    minHeight: '40px',
  };

  const bgColor = safeColor(data.background_color);
  if (bgColor) style.backgroundColor = bgColor;

  return (
    <div
      className="rounded border border-dashed border-purple-300/50"
      style={style}
    >
      {block.children.length === 0 && (
        <div className="text-xs text-purple-400/60 uppercase tracking-wide text-center py-3">
          Column — add modules
        </div>
      )}
    </div>
  );
};
