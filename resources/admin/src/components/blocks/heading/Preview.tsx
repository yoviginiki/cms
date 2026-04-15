import React, { createElement } from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const sizeMap: Record<string, string> = {
  h1: 'text-4xl',
  h2: 'text-3xl',
  h3: 'text-2xl',
  h4: 'text-xl',
  h5: 'text-lg',
  h6: 'text-base',
};

export const HeadingPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { text, level } = block.data as { text: string; level: string };

  const tag = level || 'h2';
  const sizeClass = sizeMap[tag] || sizeMap.h2;

  return createElement(
    tag,
    { className: `${sizeClass} font-bold` },
    text || 'Heading',
  );
};
