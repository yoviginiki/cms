import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField } from '@/components/editor/fields';

export const ColumnsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <SelectField
        label="Column Count"
        value={String(data.column_count ?? data.columnCount ?? 2)}
        onChange={(v) => update('column_count', parseInt(v, 10))}
        options={[
          { value: '1', label: '1 Column' },
          { value: '2', label: '2 Columns' },
          { value: '3', label: '3 Columns' },
          { value: '4', label: '4 Columns' },
          { value: '5', label: '5 Columns' },
          { value: '6', label: '6 Columns' },
        ]}
      />
      <SelectField
        label="Gap"
        value={(data.gap as string) || 'medium'}
        onChange={(v) => update('gap', v)}
        options={[
          { value: 'none', label: 'None' },
          { value: 'small', label: 'Small' },
          { value: 'medium', label: 'Medium' },
          { value: 'large', label: 'Large' },
        ]}
      />
    </div>
  );
};
