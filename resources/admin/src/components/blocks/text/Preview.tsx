import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import WysiwygEditor from '@/components/editor/WysiwygEditor';

export const TextPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const content = (block.data.content as string) || '';

  if (isSelected) {
    return (
      <div onClick={e => e.stopPropagation()}>
        <WysiwygEditor
          content={content}
          onChange={(html) => onUpdate({ ...block.data, content: html })}
          minHeight={100}
          placeholder="Type your text here..."
        />
      </div>
    );
  }

  if (!content) {
    return (
      <div className="prose max-w-none">
        <p className="text-gray-400 italic">Click to add text...</p>
      </div>
    );
  }

  return <div className="prose max-w-none" dangerouslySetInnerHTML={{ __html: content }} />;
};
