import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const CategorylistEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { style: string; showCount: boolean; parentOnly: boolean };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'links'} onChange={(e) => update('style', e.target.value)}>
          <option value="links">Links</option>
          <option value="badges">Badges</option>
          <option value="cards">Cards</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showCount} onChange={(e) => update('showCount', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Count</span>
      </label>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.parentOnly} onChange={(e) => update('parentOnly', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Parent Categories Only</span>
      </label>
    </div>
  );
};
