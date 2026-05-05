import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const sizeMap: Record<string, string> = {
  sm: '24px',
  md: '40px',
  lg: '56px',
  xl: '80px',
};

const bgShapeMap: Record<string, string> = {
  circle: '50%',
  square: '8px',
};

export const IconPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { name, size, color, background, backgroundColor } = block.data as {
    name: string;
    size: string;
    color: string;
    background: string;
    backgroundColor: string;
  };

  const dim = sizeMap[size] || sizeMap.md;
  const hasBackground = background && background !== 'none';
  const borderRadius = bgShapeMap[background] || '0';

  return (
    <div className="flex items-center justify-center p-4">
      <div
        style={{
          width: dim,
          height: dim,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          color: color || 'currentColor',
          fontSize: `calc(${dim} * 0.5)`,
          ...(hasBackground
            ? {
                backgroundColor: backgroundColor || '#e5e7eb',
                borderRadius,
                padding: '8px',
                width: `calc(${dim} + 16px)`,
                height: `calc(${dim} + 16px)`,
              }
            : {}),
        }}
      >
        <span style={{ fontSize: 'inherit', lineHeight: 1 }}>{name || 'star'}</span>
      </div>
    </div>
  );
};
