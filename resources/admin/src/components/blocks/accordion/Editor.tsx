import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';

interface AccordionItem {
  title: string;
  content: string;
}

export const AccordionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: AccordionItem[]; titleTextShadow?: string };
  const items = data.items || [{ title: 'Question', content: '<p>Answer</p>' }];

  const updateItem = (index: number, field: keyof AccordionItem, value: string) => {
    const updated = items.map((item, i) =>
      i === index ? { ...item, [field]: value } : item,
    );
    onUpdate({ ...block.data, items: updated });
  };

  const addItem = () => {
    onUpdate({
      ...block.data,
      items: [...items, { title: 'New Question', content: '<p>Answer</p>' }],
    });
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    onUpdate({
      ...block.data,
      items: items.filter((_, i) => i !== index),
    });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Title Text Shadow</label>
        <select className="select select-bordered select-sm w-full" value={data.titleTextShadow || ''} onChange={(e) => onUpdate({ ...block.data, titleTextShadow: e.target.value || undefined })}>
          <option value="">None</option>
          <option value="sm">Subtle</option>
          <option value="md">Medium</option>
          <option value="lg">Strong</option>
          <option value="outline">Outline</option>
          <option value="glow">Glow</option>
        </select>
      </div>
      <label className="block text-sm font-medium text-gray-700">Accordion Items</label>
      {items.map((item, index) => (
        <div key={index} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Item {index + 1}</span>
            <button
              type="button"
              onClick={() => removeItem(index)}
              disabled={items.length <= 1}
              className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300 disabled:cursor-not-allowed"
            >
              Remove
            </button>
          </div>
          <TextField
            label="Title"
            value={item.title}
            onChange={(v) => updateItem(index, 'title', v)}
          />
          <TextArea
            label="Content"
            value={item.content}
            onChange={(v) => updateItem(index, 'content', v)}
            rows={3}
          />
        </div>
      ))}
      <button
        type="button"
        onClick={addItem}
        className="text-sm text-blue-600 hover:text-blue-800 font-medium"
      >
        + Add Item
      </button>
    </div>
  );
};
