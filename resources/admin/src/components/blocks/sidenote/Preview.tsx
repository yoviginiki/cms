import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const SidenotePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { content, side } = block.data as { content: string; side: string };

  if (!content) {
    return (
      <aside className="text-sm text-base-content/40 italic py-2">
        Click to add side note...
      </aside>
    );
  }

  return (
    <aside
      className={`text-sm text-base-content/60 max-w-[200px] p-2 border border-base-content/10 rounded ${
        side === 'left' ? 'float-left mr-4' : 'float-right ml-4'
      }`}
    >
      <div className="text-[10px] text-base-content/30 uppercase mb-1">
        Side Note ({side})
      </div>
      {content}
    </aside>
  );
};
