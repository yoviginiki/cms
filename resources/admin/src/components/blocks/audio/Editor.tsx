import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';

export const AudioEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { url, title, artist } = block.data as {
    url: string;
    title: string;
    artist: string;
  };

  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => {
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
      <div className="grid grid-cols-3 gap-2 items-end">
        <label className="flex items-center gap-1.5 text-[11px] text-base-content/60 cursor-pointer">
          <input type="checkbox" className="checkbox checkbox-xs" checked={!!data.loop}
            onChange={(e) => update('loop', e.target.checked)} /> Loop
        </label>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Volume {Math.round(((data.volume as number) ?? 1) * 100)}%</label>
          <input type="range" min={0} max={100} value={((data.volume as number) ?? 1) * 100}
            onChange={(e) => update('volume', Number(e.target.value) / 100)}
            className="range range-xs w-full" />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Preload</label>
          <select value={(data.preload as string) || 'metadata'} onChange={(e) => update('preload', e.target.value)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="none">None</option>
            <option value="metadata">Metadata</option>
            <option value="auto">Auto</option>
          </select>
        </div>
      </div>
      <p className="text-[10px] text-base-content/40">Playback is always user-initiated — audio never autoplays.</p>
    </div>
  );
};
