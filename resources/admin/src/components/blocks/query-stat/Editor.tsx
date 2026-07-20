import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField } from '@/components/editor/fields';
import { QuerySelect } from '../collections-shared';

export const QueryStatEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Renders one number from a saved query at publish — pair with an aggregate query (count, sum, average).
      </div>
      <QuerySelect value={(data.queryId as string | null) || null} onChange={(v) => update('queryId', v)} />
      <TextField label="Label" value={(data.label as string) || ''} onChange={(v) => update('label', v)} placeholder="e.g. Works in the catalog" />
      <div className="grid grid-cols-2 gap-2">
        <TextField label="Prefix" value={(data.prefix as string) || ''} onChange={(v) => update('prefix', v)} placeholder="€" />
        <TextField label="Suffix" value={(data.suffix as string) || ''} onChange={(v) => update('suffix', v)} placeholder="+" />
      </div>
      <SelectField label="Size" value={(data.size as string) || 'lg'} onChange={(v) => update('size', v)}
        options={[
          { value: 'sm', label: 'Small' }, { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Large' }, { value: 'xl', label: 'Extra large' },
        ]} />
      <SelectField label="Align" value={(data.textAlign as string) || 'left'} onChange={(v) => update('textAlign', v)}
        options={[
          { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' },
        ]} />
    </div>
  );
};
