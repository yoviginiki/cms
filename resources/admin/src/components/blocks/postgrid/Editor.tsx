import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const PostgridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { categoryId: string; limit: number; columns: number; cardStyle: string; showExcerpt: boolean };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Category ID</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.categoryId || ''} onChange={(e) => update('categoryId', e.target.value)} placeholder="Leave empty for all" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Limit</label>
        <input type="number" className="input input-bordered input-sm w-full" value={data.limit || 6} onChange={(e) => update('limit', Number(e.target.value))} min={1} max={24} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <select className="select select-bordered select-sm w-full" value={data.columns || 3} onChange={(e) => update('columns', Number(e.target.value))}>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Card Style</label>
        <select className="select select-bordered select-sm w-full" value={data.cardStyle || 'vertical'} onChange={(e) => update('cardStyle', e.target.value)}>
          <option value="vertical">Vertical</option>
          <option value="horizontal">Horizontal</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showExcerpt} onChange={(e) => update('showExcerpt', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Excerpt</span>
      </label>
    </div>
  );
};
