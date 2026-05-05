import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const RunningtextEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { content, columns, columnGap, columnRule } = block.data as {
    content: string;
    columns: number;
    columnGap: string;
    columnRule: boolean;
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Content (HTML)</label>
        <textarea
          value={content || ''}
          onChange={(e) => onUpdate({ ...block.data, content: e.target.value })}
          rows={6}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <select
          value={columns || 2}
          onChange={(e) => onUpdate({ ...block.data, columns: Number(e.target.value) })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value={2}>2 Columns</option>
          <option value={3}>3 Columns</option>
          <option value={4}>4 Columns</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Column Gap</label>
        <input
          type="text"
          value={columnGap || '40px'}
          onChange={(e) => onUpdate({ ...block.data, columnGap: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="40px"
        />
      </div>
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={columnRule || false}
          onChange={(e) => onUpdate({ ...block.data, columnRule: e.target.checked })}
          className="checkbox checkbox-sm"
        />
        <label className="text-[11px] text-base-content/50">Column Rule</label>
      </div>
    </div>
  );
};
