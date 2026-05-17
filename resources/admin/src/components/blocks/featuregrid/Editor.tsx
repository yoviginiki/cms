import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';
import { CornerRadiusField } from '@/components/editor/fields/CornerRadiusField';
import { ShadowField } from '@/components/editor/fields/ShadowField';
import type { ShadowCustom } from '@/lib/shadowStyles';

interface FeatureItem {
  icon: string;
  title: string;
  description: string;
}

export const FeaturegridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as FeatureItem[]) || [];
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

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

  const asObj = (val: unknown): Record<string, string> =>
    (typeof val === 'object' && val !== null) ? val as Record<string, string> : {};

  return (
    <div className="space-y-3">
      {/* ── Layout ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Layout</div>
      <SelectField
        label="Columns"
        value={String(data.columns ?? 3)}
        onChange={(v) => update('columns', Number(v))}
        options={[
          { value: '1', label: '1 Column' },
          { value: '2', label: '2 Columns' },
          { value: '3', label: '3 Columns' },
          { value: '4', label: '4 Columns' },
        ]}
      />
      <SelectField
        label="Style"
        value={(data.style as string) || 'icon-top'}
        onChange={(v) => update('style', v)}
        options={[
          { value: 'icon-top', label: 'Icon Top' },
          { value: 'icon-left', label: 'Icon Left' },
        ]}
      />
      <TextField
        label="Grid Gap"
        value={(data.gap as string) || ''}
        onChange={(v) => update('gap', v)}
        placeholder="e.g. 1.5rem, 24px"
        helperText="Leave empty for default (1.5rem)"
      />

      {/* ── Card Styling ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Card Styling</div>
      <ColorField
        label="Card Background"
        value={(data.cardBgColor as string) || ''}
        onChange={(v) => update('cardBgColor', v)}
      />
      <ColorField
        label="Card Border Color"
        value={(data.cardBorderColor as string) || ''}
        onChange={(v) => update('cardBorderColor', v)}
      />
      <TextField
        label="Card Border Width"
        value={(data.cardBorderWidth as string) || ''}
        onChange={(v) => update('cardBorderWidth', v)}
        placeholder="e.g. 1px"
      />
      <CornerRadiusField
        label="Card Border Radius"
        value={asObj(data.cardBorderRadius)}
        onChange={(v) => update('cardBorderRadius', v)}
      />
      <TextField
        label="Card Padding"
        value={(data.cardPadding as string) || ''}
        onChange={(v) => update('cardPadding', v)}
        placeholder="e.g. 1.5rem, 24px"
      />
      <ShadowField
        label="Card Shadow"
        mode={(data.cardShadowMode as string) || 'preset'}
        preset={(data.cardShadow as string) || ''}
        custom={(data.cardShadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('cardShadowMode', v)}
        onChangePreset={(v) => update('cardShadow', v)}
        onChangeCustom={(v) => update('cardShadowCustom', { ...((data.cardShadowCustom as ShadowCustom) || {}), ...v })}
      />

      {/* ── Typography ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField label="Title Color" value={(data.titleColor as string) || ''} onChange={(v) => update('titleColor', v)} />
      <SelectField label="Title Text Shadow" value={(data.titleTextShadow as string) || ''} onChange={(v) => update('titleTextShadow', v)}
        options={[{ value: '', label: 'None' }, { value: 'sm', label: 'Subtle' }, { value: 'md', label: 'Medium' }, { value: 'lg', label: 'Strong' }, { value: 'outline', label: 'Outline' }, { value: 'glow', label: 'Glow' }]} />
      <ColorField label="Description Color" value={(data.descColor as string) || ''} onChange={(v) => update('descColor', v)} />
      <TextField label="Icon Size" value={(data.iconSize as string) || ''} onChange={(v) => update('iconSize', v)} placeholder="e.g. 2rem, 32px" />
      <ColorField label="Icon Color" value={(data.iconColor as string) || ''} onChange={(v) => update('iconColor', v)} />

      {/* ── Items ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Items</div>
      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Item {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <TextField label="Icon" value={item.icon} onChange={(v) => updateItem(i, 'icon', v)} placeholder="e.g. star, check, zap" />
          <TextField label="Title" value={item.title} onChange={(v) => updateItem(i, 'title', v)} />
          <TextField label="Description" value={item.description} onChange={(v) => updateItem(i, 'description', v)} />
        </div>
      ))}
      <button type="button" onClick={addItem} className="btn btn-ghost btn-sm btn-block text-[11px] border-dashed border-base-content/20">+ Add Item</button>
    </div>
  );
};
