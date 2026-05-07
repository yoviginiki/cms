import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { categories as categoriesApi } from '@/lib/api';
import type { BlockEditorProps } from '@/types/blocks';

export const LatestpostsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    categoryId: string; limit: number; columns: number;
    layout: string; orderBy: string; showImage: boolean;
    showContent: boolean; showExcerpt: boolean; excerptLength: number;
    showDate: boolean; showCategory: boolean;
  };
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
        <label className="text-[11px] text-base-content/50 mb-1 block">Number of posts</label>
        <select className="select select-bordered select-sm w-full" value={data.limit || 5} onChange={(e) => update('limit', Number(e.target.value))}>
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
          <option value={5}>5</option>
          <option value={6}>6</option>
          <option value={8}>8</option>
          <option value={10}>10</option>
          <option value={12}>12</option>
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
        <select className="select select-bordered select-sm w-full" value={data.layout || 'cards'} onChange={(e) => update('layout', e.target.value)}>
          <option value="cards">Cards</option>
          <option value="list">List</option>
          <option value="compact">Compact</option>
          <option value="featured">Featured (first large)</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Order by</label>
        <select className="select select-bordered select-sm w-full" value={data.orderBy || 'latest'} onChange={(e) => update('orderBy', e.target.value)}>
          <option value="latest">Latest first</option>
          <option value="oldest">Oldest first</option>
          <option value="title">Title A-Z</option>
          <option value="random">Random</option>
        </select>
      </div>
      <div className="space-y-2 pt-2 border-t border-base-300/20">
        <label className="flex items-center gap-2">
          <input type="checkbox" className="checkbox checkbox-sm" checked={data.showImage !== false} onChange={(e) => update('showImage', e.target.checked)} />
          <span className="text-[11px] text-base-content/50">Show image</span>
        </label>
        <label className="flex items-center gap-2">
          <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showContent} onChange={(e) => update('showContent', e.target.checked)} />
          <span className="text-[11px] text-base-content/50">Show full content</span>
        </label>
        <label className="flex items-center gap-2">
          <input type="checkbox" className="checkbox checkbox-sm" checked={data.showExcerpt !== false} onChange={(e) => update('showExcerpt', e.target.checked)} />
          <span className="text-[11px] text-base-content/50">Show excerpt</span>
        </label>
        {data.showExcerpt !== false && !data.showContent && (
          <div className="pl-6">
            <label className="text-[10px] text-base-content/40 mb-0.5 block">Excerpt length (characters)</label>
            <input type="number" className="input input-bordered input-xs w-24" min={0} max={1000}
              value={data.excerptLength ?? 120} onChange={(e) => update('excerptLength', Number(e.target.value))} />
            <span className="text-[10px] text-base-content/30 ml-1">0 = full excerpt</span>
          </div>
        )}
        <label className="flex items-center gap-2">
          <input type="checkbox" className="checkbox checkbox-sm" checked={data.showDate !== false} onChange={(e) => update('showDate', e.target.checked)} />
          <span className="text-[11px] text-base-content/50">Show date</span>
        </label>
        <label className="flex items-center gap-2">
          <input type="checkbox" className="checkbox checkbox-sm" checked={data.showCategory !== false} onChange={(e) => update('showCategory', e.target.checked)} />
          <span className="text-[11px] text-base-content/50">Show category badge</span>
        </label>
      </div>
    </div>
  );
};
