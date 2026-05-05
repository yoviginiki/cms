import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

const allPlatforms = ['twitter', 'facebook', 'linkedin', 'email', 'copy'];

export const SharebuttonsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { platforms: string[]; style: string; showLabels: boolean };
  const platforms = data.platforms || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const togglePlatform = (platform: string) => {
    const updated = platforms.includes(platform)
      ? platforms.filter((p) => p !== platform)
      : [...platforms, platform];
    update('platforms', updated);
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Platforms</label>
        <div className="space-y-1">
          {allPlatforms.map((p) => (
            <label key={p} className="flex items-center gap-2">
              <input
                type="checkbox"
                className="checkbox checkbox-sm"
                checked={platforms.includes(p)}
                onChange={() => togglePlatform(p)}
              />
              <span className="text-[11px] text-base-content/50 capitalize">{p}</span>
            </label>
          ))}
        </div>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'icons'} onChange={(e) => update('style', e.target.value)}>
          <option value="icons">Icons</option>
          <option value="buttons">Buttons</option>
          <option value="minimal">Minimal</option>
        </select>
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showLabels} onChange={(e) => update('showLabels', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Labels</span>
      </label>
    </div>
  );
};
