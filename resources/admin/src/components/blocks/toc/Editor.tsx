import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TocEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    maxDepth: number;
    style: string;
    sticky: boolean;
  };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Max Depth</label>
        <select
          className="select select-bordered select-sm w-full"
          value={String(data.maxDepth || 3)}
          onChange={(e) => update('maxDepth', Number(e.target.value))}
        >
          <option value="2">H2 only</option>
          <option value="3">H2 - H3</option>
          <option value="4">H2 - H4</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.style || 'inline'}
          onChange={(e) => update('style', e.target.value)}
        >
          <option value="inline">Inline</option>
          <option value="sidebar">Sidebar</option>
          <option value="numbered">Numbered</option>
        </select>
      </div>
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          className="checkbox checkbox-sm"
          checked={data.sticky || false}
          onChange={(e) => update('sticky', e.target.checked)}
        />
        <label className="text-[11px] text-base-content/50">Sticky position</label>
      </div>
    </div>
  );
};
