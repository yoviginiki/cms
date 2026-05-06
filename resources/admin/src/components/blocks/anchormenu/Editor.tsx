import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface AnchorItem { label: string; anchor: string }

export const AnchormenuEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { items: AnchorItem[]; style: string; sticky: boolean; smooth: boolean; offset: number };
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const updateItem = (index: number, field: keyof AnchorItem, value: string) => {
    const items = [...(data.items || [])];
    items[index] = { ...items[index], [field]: value };
    update('items', items);
  };

  const addItem = () => update('items', [...(data.items || []), { label: '', anchor: '' }]);
  const removeItem = (index: number) => update('items', (data.items || []).filter((_, i) => i !== index));

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'horizontal'}
          onChange={(e) => update('style', e.target.value)}>
          <option value="horizontal">Horizontal</option>
          <option value="vertical">Vertical</option>
          <option value="pills">Pills</option>
          <option value="underline">Underline</option>
        </select>
      </div>

      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Anchor Items</label>
        <div className="space-y-2">
          {(data.items || []).map((item, i) => (
            <div key={i} className="flex gap-1">
              <input type="text" className="input input-bordered input-xs flex-1" placeholder="Label"
                value={item.label} onChange={(e) => updateItem(i, 'label', e.target.value)} />
              <input type="text" className="input input-bordered input-xs flex-1" placeholder="#anchor"
                value={item.anchor} onChange={(e) => updateItem(i, 'anchor', e.target.value)} />
              <button className="btn btn-ghost btn-xs text-error" onClick={() => removeItem(i)}>×</button>
            </div>
          ))}
          <button className="btn btn-ghost btn-xs" onClick={addItem}>+ Add item</button>
        </div>
      </div>

      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Scroll Offset (px)</label>
        <input type="number" className="input input-bordered input-sm w-full"
          value={data.offset || 80} onChange={(e) => update('offset', Number(e.target.value))} />
      </div>

      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.sticky !== false}
          onChange={(e) => update('sticky', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Sticky on scroll</label>
      </div>
      <div className="flex items-center gap-2">
        <input type="checkbox" className="checkbox checkbox-sm" checked={data.smooth !== false}
          onChange={(e) => update('smooth', e.target.checked)} />
        <label className="text-[11px] text-base-content/50">Smooth scroll</label>
      </div>
    </div>
  );
};
