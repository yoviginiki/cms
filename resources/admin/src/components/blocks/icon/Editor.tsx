import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const IconEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { name, size, color, background, backgroundColor } = block.data as {
    name: string;
    size: string;
    color: string;
    background: string;
    backgroundColor: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Icon Name</label>
        <input
          type="text"
          value={name || ''}
          onChange={(e) => update('name', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
        <select
          value={size || 'md'}
          onChange={(e) => update('size', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="sm">Small</option>
          <option value="md">Medium</option>
          <option value="lg">Large</option>
          <option value="xl">Extra Large</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Color</label>
        <input
          type="text"
          value={color || ''}
          onChange={(e) => update('color', e.target.value)}
          placeholder="#000000"
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Background Shape</label>
        <select
          value={background || 'none'}
          onChange={(e) => update('background', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="none">None</option>
          <option value="circle">Circle</option>
          <option value="square">Square</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Background Color</label>
        <input
          type="text"
          value={backgroundColor || ''}
          onChange={(e) => update('backgroundColor', e.target.value)}
          placeholder="#e5e7eb"
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
    </div>
  );
};
