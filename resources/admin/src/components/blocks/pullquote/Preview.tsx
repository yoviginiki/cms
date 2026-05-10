import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

export const PullquotePreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const { text, attribution, style } = block.data as {
    text: string;
    attribution: string;
    style: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const styleClasses: Record<string, string> = {
    'border-left': 'border-l-4 border-base-content/20 pl-4',
    'large-text': 'text-xl font-light text-center',
    'centered': 'text-center border-y border-base-content/20 py-4',
  };

  return (
    <figure className={`pullquote py-4 ${styleClasses[style] || styleClasses['large-text']}`}>
      <blockquote>
        <InlineTextField
          as="p"
          value={text || ''}
          placeholder="Add a quote..."
          onChange={(v) => update('text', v)}
          multiline
          className="text-base-content/80"
        />
      </blockquote>
      <figcaption className="mt-2">
        <InlineTextField
          as="span"
          value={attribution || ''}
          placeholder="Author or source"
          onChange={(v) => update('attribution', v)}
          className="text-sm text-base-content/50"
        />
      </figcaption>
    </figure>
  );
};
