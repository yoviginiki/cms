import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const alignMap: Record<string, string> = {
  left: 'mr-auto',
  center: 'mx-auto',
  right: 'ml-auto',
};

export const DividerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    style: string;
    color: string;
    thickness: string;
    width: string;
    alignment: string;
  };

  const alignClass = alignMap[data.alignment] || alignMap.center;

  return (
    <div className="py-2">
      <hr
        className={alignClass}
        style={{
          borderStyle: data.style || 'solid',
          borderColor: data.color || '#d1d5db',
          borderWidth: `${data.thickness || '1px'} 0 0 0`,
          width: data.width || '100%',
        }}
      />
    </div>
  );
};
