import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const FootnotePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { content, marker } = block.data as { content: string; marker: string };

  if (!content) {
    return (
      <aside className="text-sm text-base-content/40 italic py-2">
        Click to add footnote...
      </aside>
    );
  }

  return (
    <aside className="text-sm text-base-content/60 border-t border-base-content/10 pt-2 mt-2">
      <sup className="text-base-content/80 font-bold">{marker || '*'}</sup>{' '}
      <span dangerouslySetInnerHTML={{ __html: content }} />
    </aside>
  );
};
