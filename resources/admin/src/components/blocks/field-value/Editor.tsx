import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';
import { FieldKeySelect, useTemplateCollection } from '../collections-shared';

export const FieldValueEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  // Inside a record-single/record-archive template the schema is known.
  const { data: collection } = useTemplateCollection();

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays a schema field of the current record, formatted by type — use inside a Record Page template
      </div>
      <FieldKeySelect label="Field" fields={collection?.schema.fields}
        value={(data.field as string) || ''} onChange={v => update('field', v)}
        emptyOptionLabel="— choose a field —"
        helperText="Schema key of the field to render" />
      <ToggleField label="Show Label" value={!!data.showLabel} onChange={v => update('showLabel', v)} />
      {!!data.showLabel && (
        <TextField label="Label Text" value={(data.labelText as string) || ''} onChange={v => update('labelText', v)}
          placeholder="Defaults to the field's label" />
      )}
      <TextField label="Empty Text" value={(data.emptyText as string) || ''} onChange={v => update('emptyText', v)}
        placeholder="Shown when the field has no value" />
      <SelectField label="Text Align" value={(data.textAlign as string) || 'left'} onChange={v => update('textAlign', v)}
        options={[
          { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' },
        ]} />
      <TextField label="Font Size" value={(data.fontSize as string) || ''} onChange={v => update('fontSize', v)} placeholder="e.g. 1rem" />
    </div>
  );
};
