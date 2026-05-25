import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const RelatedpostsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { limit: number; basedOn: string };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Limit</label>
        <input type="number" className="input input-bordered input-sm w-full" value={data.limit || 3} onChange={(e) => update('limit', Number(e.target.value))} min={1} max={12} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Based On</label>
        <select className="select select-bordered select-sm w-full" value={data.basedOn || 'category'} onChange={(e) => update('basedOn', e.target.value)}>
          <option value="category">Category</option>
          <option value="manual">Manual</option>
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
