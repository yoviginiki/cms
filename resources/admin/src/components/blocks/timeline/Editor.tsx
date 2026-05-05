import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface TimelineItem {
  date: string;
  title: string;
  description: string;
}

export const TimelineEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: TimelineItem[]; layout: string; lineStyle: string };
  const items = data.items || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateItem = (index: number, key: keyof TimelineItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };

  const addItem = () => {
    update('items', [...items, { date: '', title: '', description: '' }]);
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    update('items', items.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
        <select className="select select-bordered select-sm w-full" value={data.layout || 'left'} onChange={(e) => update('layout', e.target.value)}>
          <option value="left">Left</option>
          <option value="alternating">Alternating</option>
        </select>
      </div>

      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Event {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Date</label>
            <input type="text" className="input input-bordered input-sm w-full" value={item.date} onChange={(e) => updateItem(i, 'date', e.target.value)} />
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

      <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Event</button>
    </div>
  );
};
