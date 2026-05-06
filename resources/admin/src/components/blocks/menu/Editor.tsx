import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const MenuEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { menuId: string; style: string; sticky: boolean; showLogo: boolean };
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Menu ID</label>
        <input type="text" className="input input-bordered input-sm w-full" placeholder="Leave empty for primary menu"
          value={data.menuId || ''} onChange={(e) => update('menuId', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'horizontal'}
          onChange={(e) => update('style', e.target.value)}>
          <option value="horizontal">Horizontal</option>
          <option value="vertical">Vertical</option>
          <option value="hamburger">Hamburger (mobile)</option>
          <option value="dropdown">Dropdown</option>
        </select>
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.showLogo || false}
          onChange={(e) => update('showLogo', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Show logo</label>
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.sticky || false}
          onChange={(e) => update('sticky', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Sticky on scroll</label>
      </div>
    </div>
  );
};
