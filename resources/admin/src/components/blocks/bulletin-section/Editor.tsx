import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const BulletinSectionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const title = (block.data.title as string) || '';

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Section title</label>
        <input
          type="text"
          value={title}
          onChange={(e) => onUpdate({ ...block.data, title: e.target.value })}
          className="input input-bordered input-sm w-full text-[12px]"
          placeholder="e.g. This week in Sofia"
        />
      </div>
      <p className="text-[11px] text-base-content/35">
        Add event cards inside this section to list individual events.
      </p>
    </div>
  );
};
