import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const CaptionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { text, prefix } = block.data as { text: string; prefix: string };

  if (!text) {
    return (
      <figcaption className="text-sm text-base-content/40 italic">
        Click to add caption text...
      </figcaption>
    );
  }

  return (
    <figcaption className="text-sm text-base-content/50">
      {prefix && <span>{prefix} </span>}
      {text}
    </figcaption>
  );
};
