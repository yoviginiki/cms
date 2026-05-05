import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface TestimonialItem {
  quote: string;
  author: string;
  role: string;
  avatar: string;
}

export const TestimonialEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: TestimonialItem[]; layout: string };
  const items = data.items || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateItem = (index: number, key: keyof TestimonialItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };

  const addItem = () => {
    update('items', [...items, { quote: '', author: '', role: '', avatar: '' }]);
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    update('items', items.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
        <select className="select select-bordered select-sm w-full" value={data.layout || 'single'} onChange={(e) => update('layout', e.target.value)}>
          <option value="single">Single</option>
          <option value="grid">Grid</option>
          <option value="carousel">Carousel</option>
        </select>
      </div>

      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Testimonial {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Quote</label>
            <textarea className="textarea textarea-bordered textarea-sm w-full" rows={3} value={item.quote} onChange={(e) => updateItem(i, 'quote', e.target.value)} />
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Author</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.author} onChange={(e) => updateItem(i, 'author', e.target.value)} />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Role</label>
              <input type="text" className="input input-bordered input-sm w-full" value={item.role} onChange={(e) => updateItem(i, 'role', e.target.value)} />
            </div>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Avatar URL</label>
            <input type="text" className="input input-bordered input-sm w-full" value={item.avatar} onChange={(e) => updateItem(i, 'avatar', e.target.value)} />
          </div>
        </div>
      ))}

      <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Testimonial</button>
    </div>
  );
};
