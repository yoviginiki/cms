import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';
import { CornerRadiusField } from '@/components/editor/fields/CornerRadiusField';
import { ShadowField } from '@/components/editor/fields/ShadowField';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const ImageEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const asObj = (val: unknown): Record<string, string> => (typeof val === 'object' && val !== null) ? val as Record<string, string> : {};

  return (
    <div className="space-y-3">
      <AssetField label="Image" value={(data.url as string) || ''} onChange={(v) => update('url', v)} accept="image" />
      <TextField label="Alt Text" value={(data.alt as string) || ''} onChange={(v) => update('alt', v)} placeholder="Describe the image" helperText="Required for accessibility" />
      <TextField label="Caption" value={(data.caption as string) || ''} onChange={(v) => update('caption', v)} />
      <SelectField label="Size" value={(data.size as string) || 'full'} onChange={(v) => update('size', v)}
        options={[{ value: 'small', label: 'Small' }, { value: 'medium', label: 'Medium' }, { value: 'large', label: 'Large' }, { value: 'full', label: 'Full' }]} />

      <div className="divider text-[10px] text-base-content/40 my-1">Image Styling</div>
      <CornerRadiusField label="Border Radius" value={asObj(data.borderRadius)} onChange={(v) => update('borderRadius', v)} helperText="Round image corners" />
      <ShadowField label="Shadow" mode={(data.shadowMode as string) || 'preset'} preset={(data.shadow as string) || ''} custom={(data.shadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('shadowMode', v)} onChangePreset={(v) => update('shadow', v)}
        onChangeCustom={(v) => update('shadowCustom', { ...((data.shadowCustom as ShadowCustom) || {}), ...v })} />
      <ColorField label="Border Color" value={(data.borderColor as string) || ''} onChange={(v) => update('borderColor', v)} />
      <TextField label="Border Width" value={(data.borderWidth as string) || ''} onChange={(v) => update('borderWidth', v)} placeholder="e.g. 1px" />

      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(data as any).effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>
    </div>
  );
};
