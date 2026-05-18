import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ColorField, ToggleField } from '@/components/editor/fields';

export const CategoryHeaderEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays the category name, description, and post count
      </div>
      <SelectField label="Title Tag" value={(data.titleTag as string) || 'h1'} onChange={v => update('titleTag', v)}
        options={[
          { value: 'h1', label: 'H1' }, { value: 'h2', label: 'H2' }, { value: 'h3', label: 'H3' },
        ]} />
      <ColorField label="Title Color" value={(data.titleColor as string) || ''} onChange={v => update('titleColor', v)} />
      <TextField label="Title Size" value={(data.titleSize as string) || ''} onChange={v => update('titleSize', v)} placeholder="e.g. 2.5rem" />
      <SelectField label="Text Align" value={(data.textAlign as string) || 'center'} onChange={v => update('textAlign', v)}
        options={[{ value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' }]} />
      <ToggleField label="Show Description" value={data.showDescription !== false} onChange={v => update('showDescription', v)} />
      <ToggleField label="Show Post Count" value={!!data.showPostCount} onChange={v => update('showPostCount', v)} />
    </div>
  );
};
