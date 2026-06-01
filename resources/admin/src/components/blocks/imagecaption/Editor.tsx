import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const ImagecaptionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { src, alt, caption, captionPosition } = block.data as {
    src: string;
    alt: string;
    caption: string;
    captionPosition: string;
  };

  const update = (field: string, value: unknown) => {
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
        <label className="text-[11px] text-base-content/50 mb-1 block">Caption</label>
        <textarea
          value={caption || ''}
          onChange={(e) => update('caption', e.target.value)}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
          rows={2}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Caption Position</label>
        <select
          value={captionPosition || 'below'}
          onChange={(e) => update('captionPosition', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="below">Below</option>
          <option value="overlay-bottom">Overlay Bottom</option>
          <option value="side-right">Side Right</option>
          <option value="side-left">Side Left</option>
        </select>
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
