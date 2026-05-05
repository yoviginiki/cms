import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const DropcapPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { content, capSize, capColor } = block.data as {
    content: string;
    capSize: number;
    capColor: string | null;
  };

  if (!content) {
    return (
      <div className="prose max-w-none">
        <p className="text-base-content/40 italic">Click to add drop cap text...</p>
      </div>
    );
  }

  const style = `
    .dropcap-preview::first-letter {
      float: left;
      font-size: ${capSize || 3}em;
      line-height: 0.8;
      padding-right: 0.1em;
      font-weight: bold;
      ${capColor ? `color: ${capColor};` : 'color: var(--color-text);'}
    }
  `;

  return (
    <>
      <style>{style}</style>
      <div
        className="dropcap-preview prose max-w-none text-base-content/80"
        dangerouslySetInnerHTML={{ __html: content }}
      />
    </>
  );
};
