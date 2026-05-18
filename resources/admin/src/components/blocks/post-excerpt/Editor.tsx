import type { BlockEditorProps } from '@/types/blocks';
import { TextField, ColorField, SelectField } from '@/components/editor/fields';

export const PostExcerptEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays the post excerpt dynamically. The text is pulled from the post's excerpt field at render time.
      </div>
      <TextField label="Font Size" value={(data.fontSize as string) || ''} onChange={v => update('fontSize', v)} placeholder="e.g. 1.125rem" />
      <ColorField label="Color" value={(data.color as string) || ''} onChange={v => update('color', v)} />
      <SelectField label="Text Align" value={(data.textAlign as string) || ''} onChange={v => update('textAlign', v)}
        options={[
          { value: '', label: 'Default' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]} />
      <TextField label="Max Lines (line-clamp)" value={String((data.maxLines as number) || 0)} onChange={v => update('maxLines', Number(v) || 0)} placeholder="0 = no limit" />
    </div>
  );
};
