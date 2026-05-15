import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import WysiwygEditor from '@/components/editor/WysiwygEditor';

export const RichTextPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const content = (block.data.content as string) || '';

  // When selected, show full WYSIWYG editor inline
  if (isSelected) {
    return (
      <div onClick={e => e.stopPropagation()}>
        <WysiwygEditor
          content={content}
          onChange={(html) => onUpdate({ content: html })}
          minHeight={150}
          placeholder="Start typing your content... Use the toolbar for headings, bold, lists, links..."
        />
      </div>
    );
  }

  // Unselected: show rendered preview (click to select and edit)
  if (!content || content === '<p></p>') {
    return (
      <div className="rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-400 italic">
        Click to edit rich text content
      </div>
    );
  }

  return (
    <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: content }} />
  );
};
