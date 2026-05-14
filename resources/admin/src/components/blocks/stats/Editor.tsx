import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';
import { ShadowField } from '@/components/editor/fields/ShadowField';
import { CornerRadiusField } from '@/components/editor/fields/CornerRadiusField';
import type { ShadowCustom } from '@/lib/shadowStyles';

interface StatItem { value: string; label: string; prefix: string; suffix: string }

export const StatsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as StatItem[]) || [];
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const updateItem = (index: number, key: keyof StatItem, value: string) => {
    const updated = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    update('items', updated);
  };
  const addItem = () => update('items', [...items, { value: '0', label: 'Label', prefix: '', suffix: '' }]);
  const removeItem = (index: number) => { if (items.length > 1) update('items', items.filter((_, i) => i !== index)); };
  const asObj = (val: unknown): Record<string, string> => (typeof val === 'object' && val !== null) ? val as Record<string, string> : {};

  return (
    <div className="space-y-3">
      <div className="divider text-[10px] text-base-content/40 my-1">Layout</div>
      <SelectField label="Columns" value={String(data.columns ?? 3)} onChange={(v) => update('columns', Number(v))}
        options={[{ value: '1', label: '1' }, { value: '2', label: '2' }, { value: '3', label: '3' }, { value: '4', label: '4' }]} />
      <TextField label="Gap" value={(data.gap as string) || ''} onChange={(v) => update('gap', v)} placeholder="e.g. 1.5rem" helperText="Leave empty for default" />

      <div className="divider text-[10px] text-base-content/40 my-1">Card Styling</div>
      <ColorField label="Card Background" value={(data.cardBgColor as string) || ''} onChange={(v) => update('cardBgColor', v)} />
      <ColorField label="Card Border Color" value={(data.cardBorderColor as string) || ''} onChange={(v) => update('cardBorderColor', v)} />
      <CornerRadiusField label="Card Radius" value={asObj(data.cardBorderRadius)} onChange={(v) => update('cardBorderRadius', v)} />
      <ShadowField label="Card Shadow" mode={(data.cardShadowMode as string) || 'preset'} preset={(data.cardShadow as string) || ''} custom={(data.cardShadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('cardShadowMode', v)} onChangePreset={(v) => update('cardShadow', v)}
        onChangeCustom={(v) => update('cardShadowCustom', { ...((data.cardShadowCustom as ShadowCustom) || {}), ...v })} />

      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField label="Value Color" value={(data.valueColor as string) || ''} onChange={(v) => update('valueColor', v)} />
      <ColorField label="Label Color" value={(data.labelColor as string) || ''} onChange={(v) => update('labelColor', v)} />
      <TextField label="Value Font Size" value={(data.valueFontSize as string) || ''} onChange={(v) => update('valueFontSize', v)} placeholder="e.g. 2.5rem" />

      <div className="divider text-[10px] text-base-content/40 my-1">Items</div>
      {items.map((item, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Stat {i + 1}</span>
            <button type="button" onClick={() => removeItem(i)} disabled={items.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <TextField label="Value" value={item.value} onChange={(v) => updateItem(i, 'value', v)} />
            <TextField label="Label" value={item.label} onChange={(v) => updateItem(i, 'label', v)} />
          </div>
          <div className="grid grid-cols-2 gap-2">
            <TextField label="Prefix" value={item.prefix} onChange={(v) => updateItem(i, 'prefix', v)} />
            <TextField label="Suffix" value={item.suffix} onChange={(v) => updateItem(i, 'suffix', v)} />
          </div>
        </div>
      ))}
      <button type="button" onClick={addItem} className="btn btn-ghost btn-sm btn-block text-[11px] border-dashed border-base-content/20">+ Add Stat</button>
    </div>
  );
};
