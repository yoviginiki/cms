import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const CaptionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { text, prefix } = block.data as { text: string; prefix: string };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Prefix</label>
        <input
          type="text"
          value={prefix || ''}
          onChange={(e) => onUpdate({ ...block.data, prefix: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="Fig."
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Caption Text</label>
        <input
          type="text"
          value={text || ''}
          onChange={(e) => onUpdate({ ...block.data, text: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="Enter caption text"
        />
      </div>
    </div>
  );
};
