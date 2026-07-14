import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields';
import { FieldKeySelect, isImageField, useTemplateCollection } from '../collections-shared';

export const RecordImageEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  // Inside a record-single/record-archive template the schema is known.
  const { data: collection } = useTemplateCollection();
  const imageFields = collection?.schema.fields.filter(isImageField);

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Displays an image field of the current record — use inside a Record Page template
      </div>
      <FieldKeySelect label="Image Field" fields={imageFields}
        value={(data.field as string) || ''} onChange={v => update('field', v)}
        emptyOptionLabel="— first image field —"
        helperText="Leave empty to use the first image field in the schema" />
      <SelectField label="Aspect Ratio" value={(data.aspectRatio as string) || 'auto'} onChange={v => update('aspectRatio', v)}
        options={[
          { value: 'auto', label: 'Auto (natural)' }, { value: '16:9', label: '16:9' },
          { value: '4:3', label: '4:3' }, { value: '1:1', label: 'Square' }, { value: '3:2', label: '3:2' },
        ]} />
      <SelectField label="Object Fit" value={(data.objectFit as string) || 'cover'} onChange={v => update('objectFit', v)}
        options={[
          { value: 'cover', label: 'Cover (crop to fill)' }, { value: 'contain', label: 'Contain (letterbox)' },
        ]} />
    </div>
  );
};
