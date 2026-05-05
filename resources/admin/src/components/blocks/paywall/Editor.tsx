import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const PaywallEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    previewLines: number;
    blurIntensity: number;
    heading: string;
    ctaText: string;
    ctaUrl: string;
  };

  const update = (key: string, value: unknown) => {
    onUpdate({ ...block.data, [key]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Preview Lines</label>
        <input
          type="number"
          className="input input-bordered input-sm w-full"
          value={data.previewLines ?? 3}
          min={0}
          onChange={(e) => update('previewLines', parseInt(e.target.value, 10) || 0)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">
          Blur Intensity ({data.blurIntensity ?? 8}px)
        </label>
        <input
          type="range"
          className="range range-sm w-full"
          min={1}
          max={20}
          value={data.blurIntensity ?? 8}
          onChange={(e) => update('blurIntensity', parseInt(e.target.value, 10))}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Heading</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.heading || ''}
          onChange={(e) => update('heading', e.target.value)}
        />
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">CTA Text</label>
          <input
            type="text"
            className="input input-bordered input-sm w-full"
            value={data.ctaText || ''}
            onChange={(e) => update('ctaText', e.target.value)}
          />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">CTA URL</label>
          <input
            type="text"
            className="input input-bordered input-sm w-full"
            value={data.ctaUrl || ''}
            onChange={(e) => update('ctaUrl', e.target.value)}
          />
        </div>
      </div>
    </div>
  );
};
