import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';
import { ShadowField } from '@/components/editor/fields/ShadowField';
import { CornerRadiusField } from '@/components/editor/fields/CornerRadiusField';
import type { ShadowCustom } from '@/lib/shadowStyles';

interface TestimonialItem { quote: string; author: string; role: string; avatar: string }

export const TestimonialEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as TestimonialItem[]) || [];
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const updateItem = (index: number, key: keyof TestimonialItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };
  const addItem = () => update('items', [...items, { quote: '', author: '', role: '', avatar: '' }]);
  const removeItem = (index: number) => { if (items.length > 1) update('items', items.filter((_, i) => i !== index)); };
  const asObj = (val: unknown): Record<string, string> => (typeof val === 'object' && val !== null) ? val as Record<string, string> : {};

  return (
    <div className="space-y-3">
      <div className="divider text-[10px] text-base-content/40 my-1">Layout</div>
      <SelectField label="Layout" value={(data.layout as string) || 'single'} onChange={(v) => update('layout', v)}
        options={[{ value: 'single', label: 'Single' }, { value: 'grid', label: 'Grid' }, { value: 'carousel', label: 'Carousel' }]} />

      <div className="divider text-[10px] text-base-content/40 my-1">Card Styling</div>
      <ColorField label="Card Background" value={(data.cardBgColor as string) || ''} onChange={(v) => update('cardBgColor', v)} />
      <ColorField label="Card Border Color" value={(data.cardBorderColor as string) || ''} onChange={(v) => update('cardBorderColor', v)} />
      <CornerRadiusField label="Card Radius" value={asObj(data.cardBorderRadius)} onChange={(v) => update('cardBorderRadius', v)} />
      <ShadowField label="Card Shadow" mode={(data.cardShadowMode as string) || 'preset'} preset={(data.cardShadow as string) || ''} custom={(data.cardShadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('cardShadowMode', v)} onChangePreset={(v) => update('cardShadow', v)}
        onChangeCustom={(v) => update('cardShadowCustom', { ...((data.cardShadowCustom as ShadowCustom) || {}), ...v })} />

      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField label="Quote Color" value={(data.quoteColor as string) || ''} onChange={(v) => update('quoteColor', v)} />
      <ColorField label="Author Color" value={(data.authorColor as string) || ''} onChange={(v) => update('authorColor', v)} />

      <div className="divider text-[10px] text-base-content/40 my-1">Items</div>
      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Testimonial {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <TextField label="Quote" value={item.quote} onChange={(v) => updateItem(i, 'quote', v)} />
          <div className="grid grid-cols-2 gap-2">
            <TextField label="Author" value={item.author} onChange={(v) => updateItem(i, 'author', v)} />
            <TextField label="Role" value={item.role} onChange={(v) => updateItem(i, 'role', v)} />
          </div>
          <TextField label="Avatar URL" value={item.avatar} onChange={(v) => updateItem(i, 'avatar', v)} placeholder="https://..." />
        </div>
      ))}
      <button type="button" onClick={addItem} className="btn btn-ghost btn-sm btn-block text-[11px] border-dashed border-base-content/20">+ Add Testimonial</button>
    </div>
  );
};
