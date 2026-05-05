import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const HtmlEmbedPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { html: string };

  if (!data.html) {
    return (
      <div className="rounded border border-dashed border-gray-300 p-4 text-center text-sm text-gray-400 italic">
        No HTML content
      </div>
    );
  }

  return (
    <div
      className="rounded border border-gray-200 p-4"
      dangerouslySetInnerHTML={{ __html: data.html }}
    />
  );
};
