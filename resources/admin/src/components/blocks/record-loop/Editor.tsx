import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';
import {
  CollectionSelect, FieldMultiPick, FilterValueInput, SchemaLoadingHint,
  isCardField, isFilterableField, isImageField, isSortableField,
  SORT_META_OPTIONS, useCollection,
} from '../collections-shared';

export const RecordLoopEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const collectionId = (data.collectionId as string | null) || null;
  const { data: collection, isLoading: schemaLoading } = useCollection(collectionId);
  const fields = collection?.schema.fields ?? [];

  const sortOptions = [
    ...SORT_META_OPTIONS,
    ...fields.filter(isSortableField).map((f) => ({ value: f.key, label: `${f.label} (field)` })),
  ];
  const filterFields = fields.filter(isFilterableField);
  const imageFields = fields.filter(isImageField);
  const filterField = fields.find((f) => f.key === data.filterField);

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Lists published records from a collection — usable on any page
      </div>
      <CollectionSelect value={collectionId} onChange={v => update('collectionId', v)}
        unsetLabel="— inherited from archive —"
        helperText={!collectionId ? 'Inside a record template the collection is inherited — pick one to use this loop on any page' : undefined} />
      <SelectField label="Layout" value={(data.layout as string) || 'cards'} onChange={v => update('layout', v)}
        options={[
          { value: 'cards', label: 'Cards (grid)' },
          { value: 'list', label: 'List (rows)' },
          { value: 'grid', label: 'Masonry Grid' },
        ]} />
      <SelectField label="Columns" value={String(data.columns ?? 3)} onChange={v => update('columns', Number(v))}
        options={[
          { value: '1', label: '1' }, { value: '2', label: '2' }, { value: '3', label: '3' },
          { value: '4', label: '4' }, { value: '5', label: '5' }, { value: '6', label: '6' },
        ]} />
      <TextField label="Limit" value={String(data.limit ?? 12)} onChange={v => update('limit', Number(v))} placeholder="12" />
      <TextField label="Gap" value={(data.gap as string) || ''} onChange={v => update('gap', v)} placeholder="1.5rem" />

      {collectionId && schemaLoading && <SchemaLoadingHint />}

      <div className="divider text-[10px] text-base-content/40 my-1">Sorting &amp; Filtering</div>
      <SelectField label="Sort By" value={(data.sortField as string) || ''} onChange={v => update('sortField', v || null)}
        options={[{ value: '', label: 'Published date (default)' }, ...sortOptions]} />
      <SelectField label="Sort Direction" value={(data.sortDirection as string) || 'desc'} onChange={v => update('sortDirection', v)}
        options={[{ value: 'desc', label: 'Descending' }, { value: 'asc', label: 'Ascending' }]} />
      {collection && filterFields.length > 0 && (
        <>
          <SelectField label="Filter Field" value={(data.filterField as string) || ''}
            onChange={v => update('filterField', v || null)}
            options={[{ value: '', label: '— no filter —' }, ...filterFields.map((f) => ({ value: f.key, label: `${f.label} (${f.type})` }))]} />
          {filterField && (
            <FilterValueInput field={filterField}
              value={(data.filterValue as string) || ''} onChange={v => update('filterValue', v)} />
          )}
        </>
      )}

      <div className="divider text-[10px] text-base-content/40 my-1">Card Options</div>
      <ToggleField label="Show Image" value={data.showImage !== false} onChange={v => update('showImage', v)} />
      {data.showImage !== false && collection && imageFields.length > 0 && (
        <SelectField label="Image Field" value={(data.imageField as string) || ''}
          onChange={v => update('imageField', v || null)}
          options={[{ value: '', label: '— first image field —' }, ...imageFields.map((f) => ({ value: f.key, label: f.label }))]} />
      )}
      {collection && (
        <FieldMultiPick label="Card Fields" fields={fields.filter(isCardField)}
          value={(data.cardFields as string[]) || []} onChange={v => update('cardFields', v)} max={6} />
      )}
      <ToggleField label="Link to Record" value={data.linkToRecord !== false} onChange={v => update('linkToRecord', v)} />
    </div>
  );
};
