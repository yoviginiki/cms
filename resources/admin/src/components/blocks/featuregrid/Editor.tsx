import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface FeatureItem {
  icon: string;
  title: string;
  description: string;
}

export const FeaturegridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: FeatureItem[]; columns: number; style: string };
  const items = data.items || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateItem = (index: number, key: keyof FeatureItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };

  const addItem = () => {
    update('items', [...items, { icon: 'star', title: 'Feature', description: 'Description' }]);
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    update('items', items.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
          <select className="select select-bordered select-sm w-full" value={data.columns || 3} onChange={(e) => update('columns', Number(e.target.value))}>
            <option value={1}>1</option>
            <option value={2}>2</option>
            <option value={3}>3</option>
            <option value={4}>4</option>
          </select>
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
          <select className="select select-bordered select-sm w-full" value={data.style || 'icon-top'} onChange={(e) => update('style', e.target.value)}>
            <option value="icon-top">Icon Top</option>
            <option value="icon-left">Icon Left</option>
          </select>
        </div>
      </div>

      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Item {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Icon</label>
            <input type="text" className="input input-bordered input-sm w-full" value={item.icon} onChange={(e) => updateItem(i, 'icon', e.target.value)} />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Title</label>
            <input type="text" className="input input-bordered input-sm w-full" value={item.title} onChange={(e) => updateItem(i, 'title', e.target.value)} />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Description</label>
            <textarea className="textarea textarea-bordered textarea-sm w-full" rows={2} value={item.description} onChange={(e) => updateItem(i, 'description', e.target.value)} />
          </div>
        </div>
      ))}

      <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Item</button>
    </div>
  );
};
