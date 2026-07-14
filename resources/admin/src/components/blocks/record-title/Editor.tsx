import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, ColorField, TextField } from '@/components/editor/fields';

export const RecordTitleEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays the record's title dynamically — use inside a Record Page template
      </div>
      <SelectField label="HTML Tag" value={(data.tag as string) || 'h1'} onChange={v => update('tag', v)}
        options={[
          { value: 'h1', label: 'H1' }, { value: 'h2', label: 'H2' }, { value: 'h3', label: 'H3' },
          { value: 'h4', label: 'H4' }, { value: 'h5', label: 'H5' }, { value: 'h6', label: 'H6' },
        ]} />
      <ColorField label="Color" value={(data.color as string) || ''} onChange={v => update('color', v)} />
      <TextField label="Font Size" value={(data.fontSize as string) || ''} onChange={v => update('fontSize', v)} placeholder="e.g. 2.5rem" />
      <SelectField label="Text Align" value={(data.textAlign as string) || 'left'} onChange={v => update('textAlign', v)}
        options={[
          { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' },
        ]} />
    </div>
  );
};
