import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const LogostripEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { logos, grayscale, columns, gap } = block.data as {
    logos: string[];
    grayscale: boolean;
    columns: number;
    gap: string;
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
    </div>
  );
};
