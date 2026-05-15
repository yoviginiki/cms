import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import WysiwygEditor from '@/components/editor/WysiwygEditor';

export const ParagraphPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const content = (block.data.content as string) || '';

  if (isSelected) {
    return (
      <div onClick={e => e.stopPropagation()}>
        <WysiwygEditor
          content={content}
          onChange={(html) => onUpdate({ content: html })}
          minHeight={80}
          placeholder="Type your paragraph text..."
        />
      </div>
    );
  }

  if (!content) {
    return (
      <div className="prose max-w-none">
        <p className="text-base-content/40 italic">Click to add paragraph text...</p>
      </div>
    );
  }

  return <div className="prose max-w-none text-base-content/80" dangerouslySetInnerHTML={{ __html: content }} />;
};
