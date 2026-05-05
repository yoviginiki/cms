import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const FootnoteEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { content, marker } = block.data as { content: string; marker: string };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Marker (optional)</label>
        <input
          type="text"
          value={marker || ''}
          onChange={(e) => onUpdate({ ...block.data, marker: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="* (default)"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Footnote Content (HTML)</label>
        <textarea
          value={content || ''}
          onChange={(e) => onUpdate({ ...block.data, content: e.target.value })}
          rows={3}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
    </div>
  );
};
