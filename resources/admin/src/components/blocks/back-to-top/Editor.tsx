import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const BackToTopEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const d = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Label</label>
        <input type="text" value={(d.label as string) ?? ''} onChange={(e) => update('label', e.target.value)} placeholder="Back to top" className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select value={(d.style as string) || 'link'} onChange={(e) => update('style', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="link">Link</option><option value="button">Button</option><option value="icon">Icon only</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
        <select value={(d.align as string) || 'left'} onChange={(e) => update('align', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="left">Left</option><option value="center">Center</option><option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
