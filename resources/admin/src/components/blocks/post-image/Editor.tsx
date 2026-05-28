import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField } from '@/components/editor/fields';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const PostImageEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays the featured image from the post. The image source is resolved dynamically at render time.
      </div>
      <SelectField label="Image Size" value={(data.size as string) || 'full'} onChange={v => update('size', v)}
        options={[
          { value: 'full', label: 'Full' },
          { value: 'large', label: 'Large' },
          { value: 'medium', label: 'Medium' },
          { value: 'thumbnail', label: 'Thumbnail' },
        ]} />
      <TextField label="Aspect Ratio" value={(data.aspectRatio as string) || ''} onChange={v => update('aspectRatio', v)} placeholder="e.g. 16/9" />
      <TextField label="Border Radius" value={(data.borderRadius as string) || ''} onChange={v => update('borderRadius', v)} placeholder="e.g. 0.5rem" />
      <SelectField label="Object Fit" value={(data.objectFit as string) || 'cover'} onChange={v => update('objectFit', v)}
        options={[
          { value: 'cover', label: 'Cover' },
          { value: 'contain', label: 'Contain' },
          { value: 'fill', label: 'Fill' },
          { value: 'none', label: 'None' },
        ]} />
      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(block.data as any).effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>
    </div>
  );
};
