import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface StatItem {
  value: string;
  label: string;
  prefix: string;
  suffix: string;
}

export const StatsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: StatItem[]; columns: number };
  const items = data.items || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateItem = (index: number, key: keyof StatItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };

  const addItem = () => {
    update('items', [...items, { value: '0', label: 'Label', prefix: '', suffix: '' }]);
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    update('items', items.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <select className="select select-bordered select-sm w-full" value={data.columns || 3} onChange={(e) => update('columns', Number(e.target.value))}>
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
        </select>
      </div>

      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Stat {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Value</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.value} onChange={(e) => updateItem(i, 'value', e.target.value)} />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Label</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.label} onChange={(e) => updateItem(i, 'label', e.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Prefix</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.prefix} onChange={(e) => updateItem(i, 'prefix', e.target.value)} />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Suffix</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.suffix} onChange={(e) => updateItem(i, 'suffix', e.target.value)} />
            </div>
          </div>
        </div>
      ))}

      <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Stat</button>
    </div>
  );
};
