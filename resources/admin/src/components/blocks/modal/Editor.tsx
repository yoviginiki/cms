import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const ModalEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    triggerText: string;
    title: string;
    size: string;
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
        <label className="text-[11px] text-base-content/50 mb-1 block">Title</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.title || ''}
          onChange={(e) => update('title', e.target.value)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.size || 'md'}
          onChange={(e) => update('size', e.target.value)}
        >
          <option value="sm">Small</option>
          <option value="md">Medium</option>
          <option value="lg">Large</option>
        </select>
      </div>
    </div>
  );
};
