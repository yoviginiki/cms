import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const BreadcrumbsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { separator: string; showHome: boolean; homeLabel: string; showCurrent: boolean; schema: boolean };
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Separator</label>
        <select className="select select-bordered select-sm w-full" value={data.separator || '/'}
          onChange={(e) => update('separator', e.target.value)}>
          <option value="/">/</option>
          <option value="›">›</option>
          <option value="→">→</option>
          <option value=">">{'>'}</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Home Label</label>
        <input type="text" className="input input-bordered input-sm w-full"
          value={data.homeLabel || 'Home'} onChange={(e) => update('homeLabel', e.target.value)} />
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.showHome !== false}
          onChange={(e) => update('showHome', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Show Home link</label>
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.showCurrent !== false}
          onChange={(e) => update('showCurrent', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Show current page</label>
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.schema !== false}
          onChange={(e) => update('schema', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Schema.org markup</label>
      </div>
    </div>
  );
};
