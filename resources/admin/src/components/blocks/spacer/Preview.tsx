import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const heightMap: Record<string, string> = {
  sm: '16px',
  md: '32px',
  lg: '64px',
  xl: '96px',
  custom: '48px',
};

export const SpacerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { height: string };
  const px = heightMap[data.height] || heightMap.md;

  return (
    <div
      className="w-full bg-gray-100 border border-dashed border-gray-300 rounded flex items-center justify-center"
      style={{ height: px }}
    >
      <span className="text-xs text-gray-400">Spacer ({px})</span>
    </div>
  );
};
