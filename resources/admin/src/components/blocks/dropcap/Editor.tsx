import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const DropcapEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { content, capSize, capColor } = block.data as {
    content: string;
    capSize: number;
    capColor: string | null;
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Content (HTML)</label>
        <textarea
          value={content || ''}
          onChange={(e) => onUpdate({ ...block.data, content: e.target.value })}
          rows={4}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Cap Size (2-5)</label>
        <input
          type="number"
          min={2}
          max={5}
          value={capSize || 3}
          onChange={(e) => onUpdate({ ...block.data, capSize: Number(e.target.value) })}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Cap Color</label>
        <input
          type="color"
          value={capColor || '#000000'}
          onChange={(e) => onUpdate({ ...block.data, capColor: e.target.value })}
          className="input input-bordered input-sm w-16 h-8 p-1"
        />
      </div>
    </div>
  );
};
