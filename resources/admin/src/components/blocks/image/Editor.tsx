import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';

export const ImageEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { url, alt, caption, size } = block.data as {
    url: string; alt: string; caption: string; size: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <AssetField label="Image" value={url || ''} onChange={(v) => update('url', v)} accept="image" />
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Alt text</label>
        <input value={alt || ''} onChange={(e) => update('alt', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" placeholder="Describe the image" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Caption</label>
        <input value={caption || ''} onChange={(e) => update('caption', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
        <select value={size || 'full'} onChange={(e) => update('size', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]">
          <option value="small">Small</option>
          <option value="medium">Medium</option>
          <option value="large">Large</option>
          <option value="full">Full</option>
        </select>
      </div>
    </div>
  );
};
