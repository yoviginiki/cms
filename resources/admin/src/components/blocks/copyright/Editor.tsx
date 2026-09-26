import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const CopyrightEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const d = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Text</label>
        <input type="text" value={(d.text as string) ?? ''} onChange={(e) => update('text', e.target.value)}
          placeholder="© {year} {site}. All rights reserved." className="input input-bordered input-sm w-full text-[12px]" />
        <p className="text-[10px] text-base-content/40 mt-1 leading-relaxed">
          <code>{'{year}'}</code> = current year, <code>{'{site}'}</code> = site name. The year is set when the site is published.
        </p>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Since (optional) → “2019–{new Date().getFullYear()}”</label>
        <input type="number" min={1900} max={2100} value={(d.startYear as number) ?? ''}
          onChange={(e) => update('startYear', e.target.value ? Number(e.target.value) : null)} className="input input-bordered input-sm w-full text-[12px]" />
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
