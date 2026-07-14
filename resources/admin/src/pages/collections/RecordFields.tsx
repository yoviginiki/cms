import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Upload, X, Search, Loader2, ChevronUp, ChevronDown, File as FileIcon, Plus } from 'lucide-react';
import {
  DndContext, PointerSensor, useSensor, useSensors, closestCenter, type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, horizontalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import WysiwygEditor from '@/components/editor/WysiwygEditor';
import { AssetPicker } from '@/components/ui/AssetPicker';
import {
  collectionRecords,
  type CollectionField, type CollectionPivotField, type CollectionRecord,
} from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// One polished input per collection field type. Values live in the record's
// `data` map (relations separately). Media values are stored as asset UUIDs
// (the backend validates them and records asset references); display URLs are
// derived from the deterministic serve endpoint.
// ─────────────────────────────────────────────────────────────────────────────

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/** Serve URL for a stored asset id (defensive: passes through legacy URL strings). */
export function assetDisplayUrl(siteId: string, idOrUrl: string): string {
  return UUID_RE.test(idOrUrl) ? `/api/v1/sites/${siteId}/assets/${idOrUrl}/serve` : idOrUrl;
}

export interface SelectedRelation {
  id: string;
  title: string;
  pivot?: Record<string, unknown>;
}

/** Inline format hints for typed text inputs; validated for real on the server. */
function formatHint(type: CollectionField['type'], value: string): string | null {
  if (!value) return null;
  if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Doesn’t look like an email address';
  if (type === 'url' && !/^https?:\/\/\S+\.\S+/.test(value)) return 'Should start with http:// or https://';
  if (type === 'phone' && !/^[+\d][\d\s().\/-]{4,}$/.test(value)) return 'Doesn’t look like a phone number';
  return null;
}

export function FieldWrap({ field, error, children }: {
  field: Pick<CollectionField, 'label' | 'key' | 'required' | 'description' | 'settings'>;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <label className="text-[12px] text-base-content/60 mb-1 block">
        {field.label}
        {field.required && <span className="text-error ml-0.5" title="Required">*</span>}
      </label>
      {field.description && <p className="text-[11px] text-base-content/35 mb-1.5 -mt-0.5">{field.description}</p>}
      {children}
      {field.settings?.help && !error && <p className="text-[11px] text-base-content/30 mt-1">{field.settings.help}</p>}
      {error && <p className="text-[11px] text-error mt-1">{error}</p>}
    </div>
  );
}

export function FieldInput({ siteId, field, value, onChange, error, currency = 'EUR' }: {
  siteId: string;
  field: CollectionField;
  value: unknown;
  onChange: (v: unknown) => void;
  error?: string;
  currency?: string;
}) {
  const [hint, setHint] = useState<string | null>(null);
  const errCls = error ? 'input-error' : '';

  switch (field.type) {
    case 'text':
      return (
        <FieldWrap field={field} error={error}>
          <input
            value={(value as string) ?? ''}
            maxLength={field.settings?.max_length}
            placeholder={field.settings?.placeholder}
            onChange={(e) => onChange(e.target.value)}
            className={`input input-bordered input-sm w-full text-[13px] ${errCls}`}
          />
        </FieldWrap>
      );

    case 'rich_text':
      return (
        <FieldWrap field={field} error={error}>
          <WysiwygEditor
            content={(value as string) ?? ''}
            onChange={(html) => onChange(html)}
            placeholder={field.settings?.placeholder ?? 'Start typing…'}
            minHeight={Math.max(120, (field.settings?.rows ?? 8) * 20)}
          />
        </FieldWrap>
      );

    case 'number':
    case 'price': {
      const isPrice = field.type === 'price';
      return (
        <FieldWrap field={field} error={error}>
          <div className="flex items-center gap-2">
            <input
              type="number"
              inputMode="decimal"
              value={value === undefined || value === null || value === '' ? '' : String(value)}
              min={field.settings?.min}
              max={field.settings?.max}
              step={field.settings?.step ?? (isPrice ? 0.01 : undefined)}
              placeholder={field.settings?.placeholder ?? (isPrice ? '0.00' : '')}
              onChange={(e) => onChange(e.target.value === '' ? undefined : Number(e.target.value))}
              className={`input input-bordered input-sm w-40 text-[13px] tabular-nums ${errCls}`}
            />
            {isPrice && <span className="text-[13px] text-base-content/40">{currency}</span>}
          </div>
        </FieldWrap>
      );
    }

    case 'boolean':
      return (
        <FieldWrap field={field} error={error}>
          <label className="flex items-center gap-2.5 cursor-pointer w-fit">
            <input
              type="checkbox"
              className="toggle toggle-sm toggle-primary"
              checked={!!value}
              onChange={(e) => onChange(e.target.checked)}
            />
            <span className="text-[13px] text-base-content/60">{value ? 'Yes' : 'No'}</span>
          </label>
        </FieldWrap>
      );

    case 'select':
      return (
        <FieldWrap field={field} error={error}>
          <select
            value={(value as string) ?? ''}
            onChange={(e) => onChange(e.target.value || undefined)}
            className={`select select-bordered select-sm w-full max-w-xs text-[13px] ${error ? 'select-error' : ''}`}
          >
            <option value="">—</option>
            {(field.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
        </FieldWrap>
      );

    case 'multi_select': {
      const selected = Array.isArray(value) ? (value as string[]) : [];
      return (
        <FieldWrap field={field} error={error}>
          <div className="flex flex-wrap gap-1.5">
            {(field.options ?? []).map((o) => {
              const on = selected.includes(o);
              return (
                <button
                  key={o}
                  type="button"
                  onClick={() => onChange(on ? selected.filter((x) => x !== o) : [...selected, o])}
                  className={`badge badge-lg text-[12px] cursor-pointer transition-colors ${
                    on ? 'badge-primary' : 'badge-outline text-base-content/50 hover:text-base-content/80'
                  }`}
                >
                  {o}
                </button>
              );
            })}
            {(field.options ?? []).length === 0 && <span className="text-[12px] text-base-content/30">No options defined in the schema.</span>}
          </div>
        </FieldWrap>
      );
    }

    case 'date':
      return (
        <FieldWrap field={field} error={error}>
          <input
            type="date"
            value={(value as string) ?? ''}
            onChange={(e) => onChange(e.target.value || undefined)}
            className={`input input-bordered input-sm w-44 text-[13px] ${errCls}`}
          />
        </FieldWrap>
      );

    case 'email':
    case 'url':
    case 'phone':
      return (
        <FieldWrap field={field} error={error ?? hint ?? undefined}>
          <input
            type={field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'tel'}
            value={(value as string) ?? ''}
            maxLength={field.settings?.max_length}
            placeholder={field.settings?.placeholder ?? (field.type === 'url' ? 'https://…' : field.type === 'email' ? 'name@example.com' : '+359 …')}
            onChange={(e) => { onChange(e.target.value); setHint(formatHint(field.type, e.target.value)); }}
            onBlur={(e) => setHint(formatHint(field.type, e.target.value))}
            className={`input input-bordered input-sm w-full max-w-md text-[13px] ${error || hint ? 'input-error' : ''}`}
          />
        </FieldWrap>
      );

    case 'sku':
      return (
        <FieldWrap field={field} error={error}>
          <input
            value={(value as string) ?? ''}
            maxLength={field.settings?.max_length}
            placeholder={field.settings?.placeholder ?? 'SKU-0001'}
            onChange={(e) => onChange(e.target.value)}
            className={`input input-bordered input-sm w-64 text-[13px] font-mono uppercase ${errCls}`}
          />
          {!field.settings?.help && <p className="text-[11px] text-base-content/30 mt-1">Normalized to uppercase on save.</p>}
        </FieldWrap>
      );

    case 'image':
    case 'file':
      return (
        <FieldWrap field={field} error={error}>
          <SingleAssetInput
            siteId={siteId}
            value={(value as string) ?? ''}
            onChange={(assetId) => onChange(assetId || undefined)}
            accept={field.type === 'image' ? 'image' : 'all'}
          />
        </FieldWrap>
      );

    case 'gallery':
      return (
        <FieldWrap field={field} error={error}>
          <GalleryInput
            siteId={siteId}
            value={Array.isArray(value) ? (value as string[]) : []}
            onChange={(ids) => onChange(ids.length > 0 ? ids : undefined)}
          />
        </FieldWrap>
      );

    case 'relation':
      // Rendered by the page via <RelationInput> because relations live outside data{}
      return null;
  }
}

// ── Image / file: existing AssetPicker + thumbnail preview + clear ──
function SingleAssetInput({ siteId, value, onChange, accept }: {
  siteId: string;
  value: string; // asset uuid (legacy URL strings tolerated for display)
  onChange: (assetId: string) => void;
  accept: 'image' | 'all';
}) {
  const [open, setOpen] = useState(false);
  const isImage = accept === 'image';
  const displayUrl = value ? assetDisplayUrl(siteId, value) : '';

  return (
    <div>
      {value && isImage && (
        <div className="relative group w-40 mb-1.5 border border-base-300/30 rounded-box overflow-hidden">
          <img src={displayUrl} alt="" className="w-40 h-28 object-cover" onError={(e) => ((e.target as HTMLImageElement).style.opacity = '0.2')} />
          <button type="button" onClick={() => onChange('')}
            className="absolute top-1 right-1 btn btn-xs btn-circle bg-base-100/80 opacity-0 group-hover:opacity-100 transition-opacity" title="Clear">
            <X size={10} />
          </button>
        </div>
      )}
      {value && !isImage && (
        <div className="flex items-center gap-2 mb-1.5 text-[12px] text-base-content/60">
          <FileIcon size={13} className="text-base-content/40" />
          <a href={displayUrl} target="_blank" rel="noreferrer" className="truncate max-w-xs hover:text-primary hover:underline">Attached file</a>
          <button type="button" onClick={() => onChange('')} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Clear"><X size={11} /></button>
        </div>
      )}
      <button type="button" onClick={() => setOpen(true)} className="btn btn-ghost btn-xs gap-1.5 text-[12px] border border-base-300/40">
        <Upload size={11} /> {value ? 'Change' : isImage ? 'Choose image' : 'Choose file'}
      </button>
      <AssetPicker
        open={open}
        onClose={() => setOpen(false)}
        onSelect={(asset) => { onChange(asset.id); setOpen(false); }}
        accept={isImage ? 'image' : 'all'}
        currentUrl={displayUrl}
      />
    </div>
  );
}

// ── Gallery: multi image list with drag reorder + remove ──
function GalleryInput({ siteId, value, onChange }: { siteId: string; value: string[]; onChange: (ids: string[]) => void }) {
  const [open, setOpen] = useState(false);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const onDragEnd = (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    const from = value.findIndex((_, i) => `g-${i}` === active.id);
    const to = value.findIndex((_, i) => `g-${i}` === over.id);
    if (from >= 0 && to >= 0) onChange(arrayMove(value, from, to));
  };

  return (
    <div>
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={value.map((_, i) => `g-${i}`)} strategy={horizontalListSortingStrategy}>
          <div className="flex flex-wrap gap-2 mb-2">
            {value.map((assetId, i) => (
              <GalleryThumb key={`g-${i}`} id={`g-${i}`} url={assetDisplayUrl(siteId, assetId)} onRemove={() => onChange(value.filter((_, j) => j !== i))} />
            ))}
          </div>
        </SortableContext>
      </DndContext>
      <button type="button" onClick={() => setOpen(true)} className="btn btn-ghost btn-xs gap-1.5 text-[12px] border border-base-300/40">
        <Plus size={11} /> Add image
      </button>
      {value.length > 1 && <span className="text-[11px] text-base-content/30 ml-2">Drag thumbnails to reorder.</span>}
      <AssetPicker
        open={open}
        onClose={() => setOpen(false)}
        onSelect={(asset) => { onChange([...value, asset.id]); setOpen(false); }}
        accept="image"
      />
    </div>
  );
}

function GalleryThumb({ id, url, onRemove }: { id: string; url: string; onRemove: () => void }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      {...attributes}
      {...listeners}
      className={`relative group w-20 h-20 border border-base-300/30 rounded-box overflow-hidden cursor-grab active:cursor-grabbing touch-none ${isDragging ? 'opacity-50 z-10' : ''}`}
    >
      <img src={url} alt="" className="w-full h-full object-cover" loading="lazy" />
      <button
        type="button"
        onClick={(e) => { e.stopPropagation(); onRemove(); }}
        onPointerDown={(e) => e.stopPropagation()}
        className="absolute top-0.5 right-0.5 btn btn-xs btn-circle bg-base-100/80 opacity-0 group-hover:opacity-100 transition-opacity"
        title="Remove"
      >
        <X size={10} />
      </button>
    </div>
  );
}

// ── Relation: search-as-you-type picker; mode one = single chip, many = ordered rows + pivot forms ──
export function RelationInput({ siteId, field, value, onChange, error }: {
  siteId: string;
  field: CollectionField;
  value: SelectedRelation[];
  onChange: (v: SelectedRelation[]) => void;
  error?: string;
}) {
  const rel = field.relation;
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');
  const [focused, setFocused] = useState(false);

  const debounce = useMemo(() => {
    let t: ReturnType<typeof setTimeout>;
    return (v: string) => { clearTimeout(t); t = setTimeout(() => setDebounced(v), 250); };
  }, []);

  const { data: results = [], isFetching } = useQuery<CollectionRecord[]>({
    queryKey: ['relation-search', siteId, rel?.collection_id, debounced],
    queryFn: () => collectionRecords.list(siteId, rel!.collection_id, { q: debounced || undefined, per_page: 10 }).then((r) => r.data.data),
    enabled: !!rel?.collection_id && focused,
  });

  if (!rel?.collection_id) {
    return (
      <FieldWrap field={field} error={error}>
        <p className="text-[12px] text-base-content/30">This relation field has no target collection — fix it in the schema.</p>
      </FieldWrap>
    );
  }

  const selectedIds = value.map((v) => v.id);
  const candidates = results.filter((r) => !selectedIds.includes(r.id));

  const add = (r: CollectionRecord) => {
    const entry: SelectedRelation = { id: r.id, title: r.title, pivot: {} };
    onChange(rel.mode === 'one' ? [entry] : [...value, entry]);
    setQuery('');
    setDebounced('');
  };

  const showDropdown = focused && (candidates.length > 0 || isFetching || debounced.length > 0);

  return (
    <FieldWrap field={field} error={error}>
      {/* Selected — single chip (one) or ordered rows (many) */}
      {rel.mode === 'one' && value.length > 0 && (
        <div className="flex items-center gap-1.5 mb-1.5">
          <span className="badge badge-primary badge-outline badge-lg gap-1.5 text-[12px]">
            {value[0].title}
            <button type="button" onClick={() => onChange([])} className="hover:text-error" title="Remove"><X size={11} /></button>
          </span>
        </div>
      )}
      {rel.mode === 'many' && value.length > 0 && (
        <div className="space-y-1.5 mb-2">
          {value.map((sel, i) => (
            <div key={sel.id} className="border border-base-300/30 rounded-box px-2.5 py-2 bg-base-200/30">
              <div className="flex items-center gap-1.5">
                <span className="text-[13px] text-base-content/80 flex-1 truncate">{sel.title}</span>
                <button type="button" disabled={i === 0} onClick={() => onChange(arrayMove(value, i, i - 1))}
                  className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move up"><ChevronUp size={12} /></button>
                <button type="button" disabled={i === value.length - 1} onClick={() => onChange(arrayMove(value, i, i + 1))}
                  className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move down"><ChevronDown size={12} /></button>
                <button type="button" onClick={() => onChange(value.filter((_, j) => j !== i))}
                  className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Remove"><X size={12} /></button>
              </div>
              {(rel.pivot_fields?.length ?? 0) > 0 && (
                <div className="grid grid-cols-12 gap-2 mt-1.5 pt-1.5 border-t border-base-300/20">
                  {rel.pivot_fields!.map((pf) => (
                    <PivotInput
                      key={pf.key}
                      pf={pf}
                      value={sel.pivot?.[pf.key]}
                      onChange={(v) => onChange(value.map((x, j) => (j === i ? { ...x, pivot: { ...x.pivot, [pf.key]: v } } : x)))}
                    />
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Search input + dropdown */}
      {(rel.mode === 'many' || value.length === 0) && (
        <div className="relative max-w-md">
          <label className="input input-bordered input-sm flex items-center gap-2 text-[12px]">
            {isFetching ? <Loader2 className="h-3.5 w-3.5 animate-spin text-base-content/30" /> : <Search className="h-3.5 w-3.5 text-base-content/30" />}
            <input
              value={query}
              onChange={(e) => { setQuery(e.target.value); debounce(e.target.value); }}
              onFocus={() => setFocused(true)}
              onBlur={() => setTimeout(() => setFocused(false), 150)}
              placeholder="Search records to link…"
              className="grow bg-transparent"
            />
          </label>
          {showDropdown && (
            <div className="absolute z-20 left-0 right-0 mt-1 border border-base-300/50 bg-base-100 rounded-box shadow-lg max-h-56 overflow-y-auto">
              {candidates.map((r) => (
                <button
                  key={r.id}
                  type="button"
                  onMouseDown={(e) => { e.preventDefault(); add(r); }}
                  className="w-full text-left px-3 py-1.5 text-[13px] text-base-content/80 hover:bg-primary/10 flex items-center justify-between gap-2"
                >
                  <span className="truncate">{r.title || r.slug}</span>
                  {r.status === 'draft' && <span className="badge badge-ghost badge-xs text-[10px]">draft</span>}
                </button>
              ))}
              {!isFetching && candidates.length === 0 && (
                <div className="px-3 py-2 text-[12px] text-base-content/30">No matching records.</div>
              )}
            </div>
          )}
        </div>
      )}
    </FieldWrap>
  );
}

// ── Typed pivot inputs (text/number/price/boolean/select/date/sku) ──
function PivotInput({ pf, value, onChange }: {
  pf: CollectionPivotField;
  value: unknown;
  onChange: (v: unknown) => void;
}) {
  const label = (
    <span className="text-[10px] text-base-content/40 block mb-0.5">
      {pf.label}{pf.required && <span className="text-error ml-0.5">*</span>}
    </span>
  );

  switch (pf.type) {
    case 'boolean':
      return (
        <div className="col-span-3">
          {label}
          <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!value} onChange={(e) => onChange(e.target.checked)} />
        </div>
      );
    case 'select':
      return (
        <div className="col-span-4">
          {label}
          <select value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value || undefined)}
            className="select select-bordered select-xs w-full text-[12px]">
            <option value="">—</option>
            {(pf.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
        </div>
      );
    case 'number':
    case 'price':
      return (
        <div className="col-span-3">
          {label}
          <input type="number" step={pf.type === 'price' ? 0.01 : undefined}
            value={value === undefined || value === null || value === '' ? '' : String(value)}
            onChange={(e) => onChange(e.target.value === '' ? undefined : Number(e.target.value))}
            className="input input-bordered input-xs w-full text-[12px] tabular-nums" />
        </div>
      );
    case 'date':
      return (
        <div className="col-span-4">
          {label}
          <input type="date" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value || undefined)}
            className="input input-bordered input-xs w-full text-[12px]" />
        </div>
      );
    case 'sku':
      return (
        <div className="col-span-4">
          {label}
          <input value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}
            className="input input-bordered input-xs w-full text-[12px] font-mono uppercase" />
        </div>
      );
    default:
      return (
        <div className="col-span-6">
          {label}
          <input value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}
            className="input input-bordered input-xs w-full text-[12px]" />
        </div>
      );
  }
}
