import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TooltipEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    triggerText: string;
    tooltipText: string;
    position: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Trigger Text</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.triggerText || ''}
          onChange={(e) => update('triggerText', e.target.value)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Tooltip Text</label>
        <textarea
          className="textarea textarea-bordered textarea-sm w-full"
          rows={2}
          value={data.tooltipText || ''}
          onChange={(e) => update('tooltipText', e.target.value)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Position</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.position || 'top'}
          onChange={(e) => update('position', e.target.value)}
        >
          <option value="top">Top</option>
          <option value="bottom">Bottom</option>
          <option value="left">Left</option>
          <option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
