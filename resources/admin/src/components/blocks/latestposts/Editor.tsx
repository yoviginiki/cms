import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { categories as categoriesApi } from '@/lib/api';
import type { BlockEditorProps } from '@/types/blocks';

export const LatestpostsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { limit: number; layout: string; showImage: boolean; categoryId: string; columns: number };
  const { siteId = '' } = useParams();

  const { data: cats } = useQuery<Array<{ id: string; name: string }>>({
    queryKey: ['categories-for-block', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
    enabled: !!siteId,
  });

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Category</label>
        <select className="select select-bordered select-sm w-full" value={data.categoryId || ''} onChange={(e) => update('categoryId', e.target.value)}>
          <option value="">All categories</option>
          {(cats || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Limit</label>
        <select className="select select-bordered select-sm w-full" value={data.limit || 5} onChange={(e) => update('limit', Number(e.target.value))}>
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={5}>5</option>
          <option value={10}>10</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <select className="select select-bordered select-sm w-full" value={data.columns || 1} onChange={(e) => update('columns', Number(e.target.value))}>
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
        <select className="select select-bordered select-sm w-full" value={data.layout || 'list'} onChange={(e) => update('layout', e.target.value)}>
          <option value="cards">Cards</option>
          <option value="list">List</option>
          <option value="compact">Compact</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showImage} onChange={(e) => update('showImage', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Image</span>
      </label>
    </div>
  );
};
