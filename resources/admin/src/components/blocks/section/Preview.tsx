import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const paddingMap: Record<string, string> = {
  none: 'p-0',
  sm: 'p-4',
  md: 'p-8',
  lg: 'p-12',
  xl: 'p-16',
};

export const SectionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    background_color: string;
    background_image: string;
    padding: string;
    max_width: string;
    anchor_id: string;
  };

  const paddingClass = paddingMap[data.padding] || paddingMap.md;

  const style: React.CSSProperties = {};
  if (data.background_color) {
    style.backgroundColor = data.background_color;
  }
  if (data.background_image) {
    style.backgroundImage = `url(${data.background_image})`;
    style.backgroundSize = 'cover';
    style.backgroundPosition = 'center';
  }
  if (data.max_width) {
    style.maxWidth = data.max_width;
  }

  return (
    <div
      className={`rounded border border-dashed border-gray-300 ${paddingClass}`}
      style={style}
    >
      <div className="text-xs text-gray-400 uppercase tracking-wide">
        Section{data.anchor_id ? ` #${data.anchor_id}` : ''}
      </div>
      <div className="mt-2 min-h-[40px] text-sm text-gray-500 italic">
        Child blocks render here
      </div>
    </div>
  );
};
