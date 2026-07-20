import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { collections as collectionsApi, savedQueries as savedQueriesApi, themeTemplates, type Collection, type CollectionField, type CollectionFieldType, type SavedQuery } from '@/lib/api';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';

/**
 * Shared hooks + field pickers for the Track G2 collection blocks
 * (record-title, record-image, field-value, record-loop, search-box,
 * facet-filter, results-grid). Not a block folder — nothing registers here.
 */

// ─── Hooks ───

/** All collections of the current site (shared cache with the Collections pages). */
export function useSiteCollections() {
  const { siteId = '' } = useParams();
  const query = useQuery<Collection[]>({
    queryKey: ['collections', siteId],
    queryFn: () => collectionsApi.list(siteId).then((r) => r.data.data),
    enabled: !!siteId,
  });
  return { siteId, ...query };
}

/** One collection with its full schema (field pickers need schema.fields). */
export function useCollection(collectionId: string | null | undefined) {
  const { siteId = '' } = useParams();
  return useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collectionsApi.get(siteId, collectionId as string).then((r) => r.data.data),
    enabled: !!siteId && !!collectionId,
  });
}

/** Collection name for canvas previews (from the cached site list). */
export function useCollectionName(collectionId: string | null | undefined): string | null {
  const { data: list } = useSiteCollections();
  if (!collectionId) return null;
  return list?.find((c) => c.id === collectionId)?.name ?? null;
}

/**
 * The collection inherited from the template being edited (record-single /
 * record-archive templates carry collection_id). Lets the context blocks
 * (record-image, field-value) offer schema field dropdowns instead of free
 * text. Returns undefined data outside a record template.
 */
export function useTemplateCollection() {
  const { siteId = '', templateId = '' } = useParams();
  const { data: template } = useQuery<{ collection_id?: string | null }>({
    queryKey: ['template', siteId, templateId],
    queryFn: () => themeTemplates.get(siteId, templateId).then((r) => r.data?.data),
    enabled: !!siteId && !!templateId,
  });
  return useCollection(template?.collection_id ?? null);
}

// ─── Field predicates (mirror the Blade views' type rules) ───

const NOT_SORTABLE_TYPES: CollectionFieldType[] = ['relation', 'gallery', 'rich_text', 'image', 'file'];
const MEDIA_TYPES: CollectionFieldType[] = ['image', 'gallery', 'file'];
const CARD_FIELD_TYPES: CollectionFieldType[] = ['text', 'price', 'select', 'sku', 'date', 'boolean', 'number', 'relation'];

export const isSortableField = (f: CollectionField) => !NOT_SORTABLE_TYPES.includes(f.type);
export const isFilterableField = (f: CollectionField) => !MEDIA_TYPES.includes(f.type);
export const isCardField = (f: CollectionField) => CARD_FIELD_TYPES.includes(f.type);
export const isImageField = (f: CollectionField) => f.type === 'image';
export const isFacetField = (f: CollectionField) => f.facetable === true;

/** Record meta columns the backend sorts on directly (record-loop.blade.php). */
export const SORT_META_OPTIONS = [
  { value: 'published_at', label: 'Published date' },
  { value: 'created_at', label: 'Created date' },
  { value: 'updated_at', label: 'Updated date' },
  { value: 'title', label: 'Title' },
  { value: 'position', label: 'Manual order' },
];

// ─── Shared editor widgets ───

interface CollectionSelectProps {
  value: string | null;
  onChange: (id: string | null) => void;
  unsetLabel?: string;
  helperText?: string;
}

/** Saved queries of the current site (Track G-Q3). */
export function useSiteQueries() {
  const { siteId = '' } = useParams();
  const query = useQuery<SavedQuery[]>({
    queryKey: ['saved-queries', siteId],
    queryFn: () => savedQueriesApi.list(siteId).then((r) => r.data.data),
    enabled: !!siteId,
  });
  return { siteId, ...query };
}

/** Saved-query dropdown with loading/empty states (Track G-Q3). */
export function QuerySelect({ value, onChange, unsetLabel = '— choose a query —', helperText }: {
  value: string | null;
  onChange: (v: string | null) => void;
  unsetLabel?: string;
  helperText?: string;
}) {
  const { siteId, data: list, isLoading } = useSiteQueries();

  if (isLoading) {
    return <div className="text-[11px] text-base-content/40">Loading queries…</div>;
  }
  if (!list || list.length === 0) {
    return (
      <div className="text-[11px] text-base-content/40">
        No saved queries on this site yet.{' '}
        <Link to={`/sites/${siteId}/queries`} className="link link-primary">Build one</Link>
      </div>
    );
  }
  return (
    <SelectField label="Saved query" value={value || ''} onChange={(v) => onChange(v || null)}
      options={[{ value: '', label: unsetLabel }, ...list.map((q) => ({ value: q.id, label: `${q.name}${q.mode === 'sql' ? ' (SQL)' : ''}` }))]}
      helperText={helperText} />
  );
}

/** Site collection dropdown with loading/empty states. */
export function CollectionSelect({ value, onChange, unsetLabel = '— choose a collection —', helperText }: CollectionSelectProps) {
  const { siteId, data: list, isLoading } = useSiteCollections();

  if (isLoading) {
    return <div className="text-[11px] text-base-content/40">Loading collections…</div>;
  }
  if (!list || list.length === 0) {
    return (
      <div className="text-[11px] text-base-content/40">
        No collections on this site yet.{' '}
        <Link to={`/sites/${siteId}/collections`} className="link link-primary">Create one</Link>
      </div>
    );
  }
  return (
    <SelectField label="Collection" value={value || ''} onChange={(v) => onChange(v || null)}
      options={[{ value: '', label: unsetLabel }, ...list.map((c) => ({ value: c.id, label: c.name }))]}
      helperText={helperText} />
  );
}

interface FieldKeySelectProps {
  label: string;
  fields: CollectionField[] | undefined;
  value: string;
  onChange: (key: string) => void;
  emptyOptionLabel?: string;
  helperText?: string;
}

/**
 * Schema field dropdown when the schema is known; free-text key input as the
 * fallback (context blocks dropped outside a record template).
 */
export function FieldKeySelect({ label, fields, value, onChange, emptyOptionLabel = '— none —', helperText }: FieldKeySelectProps) {
  if (!fields) {
    return <TextField label={label} value={value} onChange={onChange} placeholder="e.g. cover_image" helperText={helperText} />;
  }
  return (
    <SelectField label={label} value={value} onChange={onChange}
      options={[{ value: '', label: emptyOptionLabel }, ...fields.map((f) => ({ value: f.key, label: `${f.label} (${f.type})` }))]}
      helperText={helperText} />
  );
}

interface FieldMultiPickProps {
  label: string;
  fields: CollectionField[];
  value: string[];
  onChange: (keys: string[]) => void;
  max: number;
}

/** Checkbox multi-pick of schema fields, capped at `max`. */
export function FieldMultiPick({ label, fields, value, onChange, max }: FieldMultiPickProps) {
  const toggle = (key: string) => {
    if (value.includes(key)) onChange(value.filter((k) => k !== key));
    else if (value.length < max) onChange([...value, key]);
  };
  return (
    <div>
      <label className="block text-[11px] font-medium text-base-content/50 mb-1">
        {label} <span className="opacity-60">({value.length}/{max})</span>
      </label>
      <div className="space-y-1 max-h-40 overflow-y-auto border border-base-300 rounded-md p-2">
        {fields.map((f) => {
          const checked = value.includes(f.key);
          const disabled = !checked && value.length >= max;
          return (
            <label key={f.key} className={`flex items-center gap-2 text-[11px] ${disabled ? 'opacity-40' : 'cursor-pointer'}`}>
              <input type="checkbox" className="checkbox checkbox-xs" checked={checked} disabled={disabled}
                onChange={() => toggle(f.key)} />
              <span>{f.label} <span className="text-base-content/40">({f.type})</span></span>
            </label>
          );
        })}
      </div>
    </div>
  );
}

interface FilterValueInputProps {
  field: CollectionField | undefined;
  value: string;
  onChange: (v: string) => void;
}

/** Filter value input typed to the filter field (select → options, boolean → toggle). */
export function FilterValueInput({ field, value, onChange }: FilterValueInputProps) {
  if (!field) return null;
  if (field.type === 'boolean') {
    return (
      <ToggleField label="Filter value" value={value === 'true' || value === '1'}
        onChange={(v) => onChange(v ? 'true' : 'false')}
        helperText="Match records where the toggle is on / off" />
    );
  }
  if ((field.type === 'select' || field.type === 'multi_select') && (field.options ?? []).length > 0) {
    return (
      <SelectField label="Filter value" value={value} onChange={onChange}
        options={[{ value: '', label: '— any —' }, ...(field.options ?? []).map((o) => ({ value: o, label: o }))]} />
    );
  }
  return <TextField label="Filter value" value={value} onChange={onChange} placeholder="Value to match" />;
}

/** Editor hint shown while a selected collection's schema loads. */
export function SchemaLoadingHint() {
  return <div className="text-[11px] text-base-content/40">Loading schema…</div>;
}
