import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const styleMap: Record<string, string> = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700',
  secondary: 'bg-gray-600 text-white hover:bg-gray-700',
  outline: 'border-2 border-blue-600 text-blue-600 bg-transparent',
  ghost: 'text-blue-600 bg-transparent hover:bg-blue-50',
};

const sizeMap: Record<string, string> = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-5 py-2.5 text-base',
  lg: 'px-7 py-3.5 text-lg',
};

export const ButtonPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    text: string;
    url: string;
    style: string;
    size: string;
    target: string;
  };

  const styleClass = styleMap[data.style] || styleMap.primary;
  const sizeClass = sizeMap[data.size] || sizeMap.md;

  return (
    <div className="py-2">
      <span
        className={`inline-block rounded-md font-medium cursor-pointer ${styleClass} ${sizeClass}`}
      >
        {data.text || 'Click Me'}
      </span>
      {data.url && data.url !== '#' && (
        <span className="ml-2 text-xs text-gray-400">{data.url}</span>
      )}
    </div>
  );
};
