import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const ReadingprogressEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    style: string;
    color: string;
    height: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.style || 'top-bar'}
          onChange={(e) => update('style', e.target.value)}
        >
          <option value="top-bar">Top Bar</option>
          <option value="circular">Circular</option>
          <option value="side-bar">Side Bar</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Color</label>
        <input
          type="color"
          className="input input-bordered input-sm w-full h-8"
          value={data.color || '#3b82f6'}
          onChange={(e) => update('color', e.target.value)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Height</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.height || '3px'}
          onChange={(e) => update('height', e.target.value)}
          placeholder="3px"
        />
      </div>
    </div>
  );
};
