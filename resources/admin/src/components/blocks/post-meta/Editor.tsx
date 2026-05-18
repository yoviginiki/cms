import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField, TextField, SelectField } from '@/components/editor/fields';

export const PostMetaEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays post metadata (date, author, category) dynamically from the post. Toggle each field on or off.
      </div>
      <ToggleField label="Show Date" value={data.showDate !== false} onChange={v => update('showDate', v)} />
      <ToggleField label="Show Author" value={data.showAuthor !== false} onChange={v => update('showAuthor', v)} />
      <ToggleField label="Show Category" value={data.showCategory !== false} onChange={v => update('showCategory', v)} />
      <TextField label="Separator" value={(data.separator as string) || '·'} onChange={v => update('separator', v)} placeholder="e.g. · | -" />
      <SelectField label="Text Align" value={(data.textAlign as string) || ''} onChange={v => update('textAlign', v)}
        options={[
          { value: '', label: 'Default' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]} />
    </div>
  );
};
