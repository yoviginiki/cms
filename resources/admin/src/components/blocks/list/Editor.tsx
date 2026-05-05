import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const ListEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { items, listType } = block.data as {
    items: string[];
    listType: string;
  };

  const itemsText = (items || []).join('\n');

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">List Type</label>
        <select
          value={listType || 'bullet'}
          onChange={(e) => onUpdate({ ...block.data, listType: e.target.value })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="bullet">Bullet</option>
          <option value="numbered">Numbered</option>
          <option value="checklist">Checklist</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Items (one per line)</label>
        <textarea
          value={itemsText}
          onChange={(e) =>
            onUpdate({
              ...block.data,
              items: e.target.value.split('\n').filter((line) => line.length > 0),
            })
          }
          rows={6}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
    </div>
  );
};
