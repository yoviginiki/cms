import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TableEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    headers: string[];
    rows: string[][];
    striped: boolean;
    compact: boolean;
  };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const headersStr = (data.headers || []).join(', ');
  const rowsStr = (data.rows || []).map((r) => r.join('\t')).join('\n');

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Headers (comma-separated)</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={headersStr}
          onChange={(e) => update('headers', e.target.value.split(',').map((s) => s.trim()))}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Rows (tab-separated columns, one row per line)</label>
        <textarea
          className="textarea textarea-bordered textarea-sm w-full"
          rows={5}
          value={rowsStr}
          onChange={(e) =>
            update(
              'rows',
              e.target.value.split('\n').map((line) => line.split('\t')),
            )
          }
        />
      </div>
      <div className="flex gap-4">
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            className="checkbox checkbox-sm"
            checked={!!data.striped}
            onChange={(e) => update('striped', e.target.checked)}
          />
          <span className="text-[11px] text-base-content/50">Striped</span>
        </label>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            className="checkbox checkbox-sm"
            checked={!!data.compact}
            onChange={(e) => update('compact', e.target.checked)}
          />
          <span className="text-[11px] text-base-content/50">Compact</span>
        </label>
      </div>
    </div>
  );
};
