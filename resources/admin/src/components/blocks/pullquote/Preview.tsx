import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const PullquotePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { text, attribution, style } = block.data as {
    text: string;
    attribution: string;
    style: string;
  };

  if (!text) {
    return (
      <div className="text-base-content/40 italic py-4">
        Click to add a pull quote...
      </div>
    );
  }

  const styleClasses: Record<string, string> = {
    'border-left': 'border-l-4 border-base-content/20 pl-4',
    'large-text': 'text-xl font-light text-center',
    'centered': 'text-center border-y border-base-content/20 py-4',
  };

  return (
    <figure className={`pullquote py-4 ${styleClasses[style] || styleClasses['large-text']}`}>
      <blockquote className="text-base-content/80">
        {text}
      </blockquote>
      {attribution && (
        <figcaption className="text-sm text-base-content/50 mt-2">
          {attribution}
        </figcaption>
      )}
    </figure>
  );
};
