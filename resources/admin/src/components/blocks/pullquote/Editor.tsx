import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const PullquoteEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { text, attribution, style } = block.data as {
    text: string;
    attribution: string;
    style: string;
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Quote Text</label>
        <textarea
          value={text || ''}
          onChange={(e) => onUpdate({ ...block.data, text: e.target.value })}
          rows={4}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Attribution</label>
        <input
          type="text"
          value={attribution || ''}
          onChange={(e) => onUpdate({ ...block.data, attribution: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="Author or source"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select
          value={style || 'large-text'}
          onChange={(e) => onUpdate({ ...block.data, style: e.target.value })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="border-left">Border Left</option>
          <option value="large-text">Large Text</option>
          <option value="centered">Centered</option>
        </select>
      </div>
    </div>
  );
};
