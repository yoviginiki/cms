import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const SidenoteEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { content, side } = block.data as { content: string; side: string };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Content</label>
        <textarea
          value={content || ''}
          onChange={(e) => onUpdate({ ...block.data, content: e.target.value })}
          rows={3}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Side</label>
        <select
          value={side || 'right'}
          onChange={(e) => onUpdate({ ...block.data, side: e.target.value })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="left">Left</option>
          <option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
