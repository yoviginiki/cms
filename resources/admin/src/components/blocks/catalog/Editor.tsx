import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';
import { SelectField } from '@/components/editor/fields/SelectField';

interface CatalogItem {
  title: string;
  subtitle: string;
  content: string;
  contentSecondary: string;
  images: string[];
}

export const CatalogEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    items: CatalogItem[];
    headerLabels: string[];
    openFirst: boolean;
    imageHeight: string;
    imageFilter: string;
    imageHoverReveal: boolean;
  };

  const items = data.items || [];
  const headerLabels = data.headerLabels || ['no.', 'title', 'subtitle', ''];

  const updateItem = (index: number, field: keyof CatalogItem, value: unknown) => {
    const updated = items.map((item, i) =>
      i === index ? { ...item, [field]: value } : item,
    );
    onUpdate({ ...block.data, items: updated });
  };

  const addItem = () => {
    onUpdate({
      ...block.data,
      items: [...items, { title: 'New Item', subtitle: '', content: '<p>Description</p>', contentSecondary: '', images: [] }],
    });
  };

  const removeItem = (index: number) => {
    if (items.length <= 1) return;
    onUpdate({ ...block.data, items: items.filter((_, i) => i !== index) });
  };

  const updateImage = (itemIndex: number, imgIndex: number, value: string) => {
    const imgs = [...(items[itemIndex].images || [])];
    imgs[imgIndex] = value;
    updateItem(itemIndex, 'images', imgs);
  };

  const addImage = (itemIndex: number) => {
    const imgs = [...(items[itemIndex].images || []), ''];
    updateItem(itemIndex, 'images', imgs);
  };

  const removeImage = (itemIndex: number, imgIndex: number) => {
    const imgs = (items[itemIndex].images || []).filter((_, i) => i !== imgIndex);
    updateItem(itemIndex, 'images', imgs);
  };

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <SelectField
          label="Image Filter"
          value={data.imageFilter || 'grayscale'}
          onChange={(v) => onUpdate({ ...block.data, imageFilter: v })}
          options={[
            { value: 'none', label: 'None' },
            { value: 'grayscale', label: 'Grayscale' },
            { value: 'sepia', label: 'Sepia' },
          ]}
        />
        <TextField
          label="Image Height"
          value={data.imageHeight || '280px'}
          onChange={(v) => onUpdate({ ...block.data, imageHeight: v })}
        />
      </div>
      <div className="flex gap-3">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={data.openFirst !== false}
            onChange={(e) => onUpdate({ ...block.data, openFirst: e.target.checked })}
            className="checkbox checkbox-sm"
          />
          Open first item
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={data.imageHoverReveal !== false}
            onChange={(e) => onUpdate({ ...block.data, imageHoverReveal: e.target.checked })}
            className="checkbox checkbox-sm"
          />
          Hover reveal (color on hover)
        </label>
      </div>

      <div className="border-t pt-3">
        <label className="block text-sm font-medium text-gray-700 mb-2">Header Labels</label>
        <div className="grid grid-cols-4 gap-2">
          {headerLabels.map((label, i) => (
            <TextField
              key={i}
              label={`Col ${i + 1}`}
              value={label}
              onChange={(v) => {
                const updated = [...headerLabels];
                updated[i] = v;
                onUpdate({ ...block.data, headerLabels: updated });
              }}
            />
          ))}
        </div>
      </div>

      <div className="border-t pt-3">
        <label className="block text-sm font-medium text-gray-700 mb-2">Items</label>
        {items.map((item, index) => (
          <div key={index} className="rounded border border-gray-200 p-3 space-y-2 mb-3">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase">
                {String(index + 1).padStart(2, '0')} — {item.title || 'Untitled'}
              </span>
              <button
                type="button"
                onClick={() => removeItem(index)}
                disabled={items.length <= 1}
                className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300"
              >
                Remove
              </button>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <TextField label="Title" value={item.title} onChange={(v) => updateItem(index, 'title', v)} />
              <TextField label="Subtitle" value={item.subtitle || ''} onChange={(v) => updateItem(index, 'subtitle', v)} />
            </div>
            <TextArea label="Content (primary)" value={item.content} onChange={(v) => updateItem(index, 'content', v)} rows={3} />
            <TextArea label="Content (secondary)" value={item.contentSecondary || ''} onChange={(v) => updateItem(index, 'contentSecondary', v)} rows={3} />
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Images</label>
              {(item.images || []).map((img, imgI) => (
                <div key={imgI} className="flex gap-1 mb-1">
                  <input
                    className="input input-bordered input-sm flex-1"
                    value={img}
                    onChange={(e) => updateImage(index, imgI, e.target.value)}
                    placeholder="Image URL"
                  />
                  <button type="button" onClick={() => removeImage(index, imgI)} className="text-xs text-red-500 px-2">×</button>
                </div>
              ))}
              <button type="button" onClick={() => addImage(index)} className="text-xs text-blue-600 hover:text-blue-800">+ Add Image</button>
            </div>
          </div>
        ))}
        <button type="button" onClick={addItem} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Item</button>
      </div>
    </div>
  );
};
