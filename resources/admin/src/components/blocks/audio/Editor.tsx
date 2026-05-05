import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';

export const AudioEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { url, title, artist } = block.data as {
    url: string;
    title: string;
    artist: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <AssetField label="Audio file" value={url || ''} onChange={(v) => update('url', v)} accept="audio" />
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Title</label>
        <input
          type="text"
          value={title || ''}
          onChange={(e) => update('title', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Artist</label>
        <input
          type="text"
          value={artist || ''}
          onChange={(e) => update('artist', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
    </div>
  );
};
