import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface ChartDataItem {
  label: string;
  value: number;
}

export const ChartEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { chartType: string; data: ChartDataItem[]; title: string; showLegend: boolean };
  const items = data.data || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateItem = (index: number, key: keyof ChartDataItem, value: string | number) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('data', updated);
  };

  const addItem = () => {
    update('data', [...items, { label: '', value: 0 }]);
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    update('data', items.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Chart Type</label>
        <select className="select select-bordered select-sm w-full" value={data.chartType || 'bar'} onChange={(e) => update('chartType', e.target.value)}>
          <option value="bar">Bar</option>
          <option value="line">Line</option>
          <option value="pie">Pie</option>
          <option value="donut">Donut</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Title</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.title || ''} onChange={(e) => update('title', e.target.value)} />
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={!!data.showLegend} onChange={(e) => update('showLegend', e.target.checked)} />
        <span className="text-[11px] text-base-content/50">Show Legend</span>
      </label>

      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Data</label>
        {items.map((item, i) => (
          <div key={i} className="flex items-center gap-2 mb-2">
            <input type="text" className="input input-bordered input-sm flex-1" placeholder="Label" value={item.label} onChange={(e) => updateItem(i, 'label', e.target.value)} />
            <input type="number" className="input input-bordered input-sm w-20" value={item.value} onChange={(e) => updateItem(i, 'value', Number(e.target.value))} />
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">x</button>
          </div>
        ))}
        <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Data Point</button>
      </div>
    </div>
  );
};
