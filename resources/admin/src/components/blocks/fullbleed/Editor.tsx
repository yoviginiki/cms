import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const FullbleedEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { src, alt, overlayText, overlayPosition, scrimOpacity, minHeight } = block.data as {
    src: string;
    alt: string;
    overlayText: string;
    overlayPosition: string;
    scrimOpacity: number;
    minHeight: string;
  };

  const update = (field: string, value: string | number) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <AssetField label="Image" value={src || ''} onChange={(v) => update('src', v)} accept="image" />
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Alt Text</label>
        <input
          type="text"
          value={alt || ''}
          onChange={(e) => update('alt', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Overlay Text</label>
        <textarea
          value={overlayText || ''}
          onChange={(e) => update('overlayText', e.target.value)}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
          rows={3}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Overlay Position</label>
        <select
          value={overlayPosition || 'center'}
          onChange={(e) => update('overlayPosition', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="center">Center</option>
          <option value="bottom-left">Bottom Left</option>
          <option value="bottom-right">Bottom Right</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">
          Scrim Opacity: {scrimOpacity ?? 0.4}
        </label>
        <input
          type="range"
          min="0"
          max="1"
          step="0.05"
          value={scrimOpacity ?? 0.4}
          onChange={(e) => update('scrimOpacity', parseFloat(e.target.value))}
          className="range range-sm w-full"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Min Height</label>
        <input
          type="text"
          value={minHeight || '60vh'}
          onChange={(e) => update('minHeight', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(block.data as any).effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>
    </div>
  );
};
