import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

const sizeMap: Record<string, string> = {
  h1: 'text-4xl',
  h2: 'text-3xl',
  h3: 'text-2xl',
  h4: 'text-xl',
  h5: 'text-lg',
  h6: 'text-base',
};

export const HeadingPreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const { text, level } = block.data as { text: string; level: string };

  const tag = (level || 'h2') as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
  const sizeClass = sizeMap[tag] || sizeMap.h2;

  return (
    <InlineTextField
      as={tag}
      value={text || ''}
      placeholder="Add heading"
      onChange={(v) => onUpdate({ ...block.data, text: v })}
      className={`${sizeClass} font-bold block`}
    />
  );
};
