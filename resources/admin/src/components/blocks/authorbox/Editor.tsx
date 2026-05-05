import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const AuthorboxEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { showAvatar: boolean; showBio: boolean; showSocialLinks: boolean; layout: string };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
        <select className="select select-bordered select-sm w-full" value={data.layout || 'horizontal'} onChange={(e) => update('layout', e.target.value)}>
          <option value="horizontal">Horizontal</option>
          <option value="vertical">Vertical</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showAvatar} onChange={(e) => update('showAvatar', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Avatar</span>
      </label>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showBio} onChange={(e) => update('showBio', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Bio</span>
      </label>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showSocialLinks} onChange={(e) => update('showSocialLinks', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Social Links</span>
      </label>
    </div>
  );
};
