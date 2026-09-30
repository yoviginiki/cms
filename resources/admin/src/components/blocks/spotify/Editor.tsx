import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { parseSpotify } from '@/lib/spotify';

export const SpotifyEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { url?: string; theme?: string; size?: string; height?: number };
  const update = (key: string, value: unknown) => onUpdate({ ...block.data, [key]: value });
  const parsed = parseSpotify(data.url);

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Spotify link</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.url || ''}
          onChange={(e) => update('url', e.target.value.trim())}
          placeholder="https://open.spotify.com/playlist/..."
        />
        <p className={`text-[10px] mt-1 ${data.url && !parsed ? 'text-error' : 'text-base-content/40'}`}>
          {data.url && !parsed
            ? 'Not recognised — in Spotify use Share → Copy link.'
            : parsed
              ? `✓ ${parsed.type}`
              : 'Spotify → Share → Copy link (playlist, album, song, artist, podcast). Embed code works too.'}
        </p>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Color</label>
          <select className="select select-bordered select-sm w-full" value={data.theme || 'color'}
            onChange={(e) => update('theme', e.target.value)}>
            <option value="color">From cover</option>
            <option value="dark">Dark</option>
          </select>
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
          <select className="select select-bordered select-sm w-full" value={data.size || 'normal'}
            onChange={(e) => update('size', e.target.value)}>
            <option value="normal">Normal</option>
            <option value="compact">Compact</option>
          </select>
        </div>
      </div>
    </div>
  );
};
