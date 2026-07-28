import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const LogostripEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { logos, grayscale, columns, gap, sizeMode, logoSize } = block.data as {
    logos: string[];
    grayscale: boolean;
    columns: number;
    gap: string;
    sizeMode?: 'height' | 'width';
    logoSize?: number;
  };

  const logoList = Array.isArray(logos) ? logos : [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Logos (one URL per line)</label>
        <textarea
          value={logoList.join('\n')}
          onChange={(e) =>
            update(
              'logos',
              e.target.value
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
            )
          }
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
          rows={5}
        />
      </div>
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={grayscale ?? true}
          onChange={(e) => update('grayscale', e.target.checked)}
          className="checkbox checkbox-sm"
        />
        <label className="text-[11px] text-base-content/50">Grayscale</label>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Logo size</label>
        <div className="flex gap-2">
          <select
            value={sizeMode ?? 'height'}
            onChange={(e) => update('sizeMode', e.target.value)}
            className="select select-bordered select-sm text-[12px]">
            <option value="height">Height</option>
            <option value="width">Width</option>
          </select>
          <div className="join flex-1">
            <input
              type="number"
              min={8}
              max={600}
              value={logoSize ?? 48}
              onChange={(e) => update('logoSize', parseInt(e.target.value, 10) || 48)}
              className="input input-bordered input-sm join-item w-full text-[12px]"
            />
            <span className="join-item inline-flex items-center px-2 text-[11px] bg-base-200 border border-base-300">px</span>
          </div>
        </div>
        <p className="text-[10px] text-base-content/40 mt-1">All logos get the same {sizeMode === 'width' ? 'width' : 'height'}; the other side scales to keep proportions.</p>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <input
          type="number"
          min={2}
          max={8}
          value={columns || 4}
          onChange={(e) => update('columns', parseInt(e.target.value, 10))}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Gap</label>
        <input
          type="text"
          value={gap || '32px'}
          onChange={(e) => update('gap', e.target.value)}
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
