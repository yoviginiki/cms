import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const TextPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { content } = block.data as { content: string };

  if (!content) {
    return (
      <div className="prose max-w-none">
        <p className="text-gray-400 italic">Click to add text...</p>
      </div>
    );
  }

  return (
    <div
      className="prose max-w-none"
      dangerouslySetInnerHTML={{ __html: content }}
    />
  );
};
