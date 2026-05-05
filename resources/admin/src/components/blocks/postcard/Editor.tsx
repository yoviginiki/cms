import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const PostcardEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { postId: string; style: string; showExcerpt: boolean; showDate: boolean; showCategory: boolean };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Post ID</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.postId || ''} onChange={(e) => update('postId', e.target.value)} placeholder="Enter post ID" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'vertical'} onChange={(e) => update('style', e.target.value)}>
          <option value="vertical">Vertical</option>
          <option value="horizontal">Horizontal</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showExcerpt} onChange={(e) => update('showExcerpt', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Excerpt</span>
      </label>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showDate} onChange={(e) => update('showDate', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Date</span>
      </label>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showCategory} onChange={(e) => update('showCategory', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Category</span>
      </label>
    </div>
  );
};
