import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const SocialembedEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    url: string;
    platform: string;
  };

  const update = (key: string, value: unknown) => {
    onUpdate({ ...block.data, [key]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">URL</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.url || ''}
          onChange={(e) => update('url', e.target.value)}
          placeholder="https://twitter.com/..."
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Platform</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.platform || 'auto'}
          onChange={(e) => update('platform', e.target.value)}
        >
          <option value="auto">Auto-detect</option>
          <option value="twitter">Twitter / X</option>
          <option value="instagram">Instagram</option>
          <option value="youtube">YouTube</option>
          <option value="tiktok">TikTok</option>
        </select>
      </div>
    </div>
  );
};
