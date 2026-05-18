import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, ColorField, TextField } from '@/components/editor/fields';

export const PostTitleEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays the post title dynamically from post data
      </div>
      <SelectField label="HTML Tag" value={(data.tag as string) || 'h1'} onChange={v => update('tag', v)}
        options={[
          { value: 'h1', label: 'H1' }, { value: 'h2', label: 'H2' }, { value: 'h3', label: 'H3' },
          { value: 'h4', label: 'H4' }, { value: 'h5', label: 'H5' }, { value: 'h6', label: 'H6' },
        ]} />
      <ColorField label="Color" value={(data.color as string) || ''} onChange={v => update('color', v)} />
      <TextField label="Font Size" value={(data.fontSize as string) || ''} onChange={v => update('fontSize', v)} placeholder="e.g. 2.5rem" />
      <SelectField label="Font Weight" value={(data.fontWeight as string) || ''} onChange={v => update('fontWeight', v)}
        options={[
          { value: '', label: 'Default' }, { value: '400', label: 'Normal' }, { value: '500', label: 'Medium' },
          { value: '600', label: 'Semibold' }, { value: '700', label: 'Bold' }, { value: '800', label: 'Extra Bold' },
        ]} />
      <SelectField label="Text Align" value={(data.textAlign as string) || ''} onChange={v => update('textAlign', v)}
        options={[
          { value: '', label: 'Default' }, { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' },
        ]} />
      <SelectField label="Text Shadow" value={(data.textShadow as string) || ''} onChange={v => update('textShadow', v)}
        options={[
          { value: '', label: 'None' }, { value: 'sm', label: 'Subtle' }, { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Strong' }, { value: 'outline', label: 'Outline' }, { value: 'glow', label: 'Glow' },
        ]} />
    </div>
  );
};
