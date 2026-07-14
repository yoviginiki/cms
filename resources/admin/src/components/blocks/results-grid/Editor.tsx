import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';
import { CollectionSelect, FieldMultiPick, SchemaLoadingHint, isCardField, useCollection } from '../collections-shared';

export const ResultsGridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const collectionId = (data.collectionId as string | null) || null;
  const { data: collection, isLoading: schemaLoading } = useCollection(collectionId);

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Renders live search results — pair with Search Box and Facet Filter on the same page
      </div>
      <CollectionSelect value={collectionId} onChange={v => update('collectionId', v)}
        unsetLabel="— inherited from archive —"
        helperText={!collectionId ? 'Inside a record template the collection is inherited from the template' : undefined} />
      <SelectField label="Columns" value={String(data.columns ?? 3)} onChange={v => update('columns', Number(v))}
        options={[
          { value: '1', label: '1' }, { value: '2', label: '2' }, { value: '3', label: '3' },
          { value: '4', label: '4' }, { value: '5', label: '5' }, { value: '6', label: '6' },
        ]} />
      <ToggleField label="Show Image" value={data.showImage !== false} onChange={v => update('showImage', v)} />
      {collectionId && schemaLoading && <SchemaLoadingHint />}
      {collection && (
        <FieldMultiPick label="Card Fields" fields={collection.schema.fields.filter(isCardField)}
          value={(data.cardFields as string[]) || []} onChange={v => update('cardFields', v)} max={6} />
      )}
      <TextField label="Empty Text" value={(data.emptyText as string) || ''} onChange={v => update('emptyText', v)}
        placeholder="No results — try a different search." />
    </div>
  );
};
