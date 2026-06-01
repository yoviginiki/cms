import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const BeforeafterEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { beforeSrc, afterSrc, beforeLabel, afterLabel, initialPosition } = block.data as {
    beforeSrc: string;
    afterSrc: string;
    beforeLabel: string;
    afterLabel: string;
    initialPosition: number;
  };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <AssetField label="Before image" value={beforeSrc || ''} onChange={(v) => update('beforeSrc', v)} accept="image" />
      <AssetField label="After image" value={afterSrc || ''} onChange={(v) => update('afterSrc', v)} accept="image" />
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Before Label</label>
        <input
          type="text"
          value={beforeLabel || 'Before'}
          onChange={(e) => update('beforeLabel', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">After Label</label>
        <input
          type="text"
          value={afterLabel || 'After'}
          onChange={(e) => update('afterLabel', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">
          Initial Position: {initialPosition ?? 50}%
        </label>
        <input
          type="range"
          min="0"
          max="100"
          step="1"
          value={initialPosition ?? 50}
          onChange={(e) => update('initialPosition', parseInt(e.target.value, 10))}
          className="range range-sm w-full"
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
