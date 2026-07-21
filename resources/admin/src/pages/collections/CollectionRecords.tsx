import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  Plus, Upload, Download, Trash2, Loader2, Search, ArrowUp, ArrowDown, ArrowUpDown,
  Pencil, Database, Check, ArrowLeft, Eye, EyeOff, Image as ImageIcon, File as FileIcon,
  Copy, CalendarClock, TimerOff, ListPlus,
} from 'lucide-react';
import {
  collections, collectionRecords,
  type Collection, type CollectionField, type CollectionRecord, type CollectionRecordPayload,
} from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useToast } from '@/components/ui/Toast';
import { Modal, apiErr, validationErrors } from './shared';
import { isSortableType, isEditableScalar } from '@/lib/collectionFieldTypes';

interface RecordsMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

type StatusTab = '' | 'published' | 'draft';

const PER_PAGE_CHOICES = [25, 50, 100];

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/** Image/file values are asset UUIDs → deterministic serve URL (legacy URL strings pass through). */
function assetUrl(siteId: string, v: unknown): string | null {
  if (typeof v === 'string' && v) return UUID_RE.test(v) ? `/api/v1/sites/${siteId}/assets/${v}/serve` : v;
  if (v && typeof v === 'object' && typeof (v as any).url === 'string') return (v as any).url;
  return null;
}

function TypedCell({ siteId, field, record }: { siteId: string; field: CollectionField; record: CollectionRecord }) {
  const v = record.data?.[field.key];
  switch (field.type) {
    case 'price': {
      if (v === null || v === undefined || v === '') return <span className="text-base-content/25">—</span>;
      const n = Number(v);
      return <span className="tabular-nums">{Number.isFinite(n) ? `${n.toFixed(2)} €` : String(v)}</span>;
    }
    case 'boolean':
      return v ? <Check size={14} className="text-success" /> : <span className="text-base-content/25">—</span>;
    case 'date':
      return <span>{v ? new Date(String(v)).toLocaleDateString() : <span className="text-base-content/25">—</span>}</span>;
    case 'multi_select':
      return <span className="truncate">{Array.isArray(v) && v.length > 0 ? v.join(', ') : <span className="text-base-content/25">—</span>}</span>;
    case 'relation': {
      const n = record.relations?.[field.key]?.length ?? 0;
      return n > 0 ? <span className="badge badge-ghost badge-xs text-[11px]">{n} linked</span> : <span className="text-base-content/25">—</span>;
    }
    case 'image': {
      const url = assetUrl(siteId, v);
      return url
        ? <img src={url} alt="" className="w-8 h-8 object-cover rounded border border-base-300/30" loading="lazy" />
        : <ImageIcon size={14} className="text-base-content/15" />;
    }
    case 'gallery': {
      const arr = Array.isArray(v) ? v : [];
      const first = arr.length > 0 ? assetUrl(siteId, arr[0]) : null;
      return arr.length > 0 ? (
        <span className="flex items-center gap-1.5">
          {first && <img src={first} alt="" className="w-8 h-8 object-cover rounded border border-base-300/30" loading="lazy" />}
          <span className="text-[11px] text-base-content/40">{arr.length}</span>
        </span>
      ) : <span className="text-base-content/25">—</span>;
    }
    case 'file': {
      const url = assetUrl(siteId, v);
      return url
        ? <span className="flex items-center gap-1 text-[12px] text-base-content/60"><FileIcon size={12} /> file</span>
        : <span className="text-base-content/25">—</span>;
    }
    case 'rich_text': {
      const text = typeof v === 'string' ? v.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';
      return text ? <span className="truncate block max-w-48">{text.slice(0, 80)}</span> : <span className="text-base-content/25">—</span>;
    }
    case 'sku':
      return v ? <code className="text-[12px] font-mono">{String(v)}</code> : <span className="text-base-content/25">—</span>;
    default:
      return v !== undefined && v !== null && v !== ''
        ? <span className="truncate block max-w-48">{String(v)}</span>
        : <span className="text-base-content/25">—</span>;
  }
}

/** Full save payload for a list row with one data key replaced — keeps relations/status intact. */
function rowPayloadWith(r: CollectionRecord, key: string, value: unknown): CollectionRecordPayload {
  return {
    data: { ...(r.data ?? {}), [key]: value },
    relations: Object.fromEntries(
      Object.entries(r.relations ?? {}).map(([k, items]) => [
        k,
        [...items]
          .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
          .map((it) => ({
            id: it.id,
            ...(it.pivot && Object.keys(it.pivot).length > 0 ? { pivot: it.pivot as Record<string, unknown> } : {}),
          })),
      ]),
    ),
    status: r.status,
  };
}

/** Typed input used by inline cell editing and the bulk "Set field" modal. */
function ScalarValueInput({ field, value, onChange, onCommit, onCancel, autoFocus, invalid, size = 'xs' }: {
  field: CollectionField;
  value: unknown;
  onChange: (v: unknown) => void;
  onCommit?: () => void;
  onCancel?: () => void;
  autoFocus?: boolean;
  invalid?: boolean;
  size?: 'xs' | 'sm';
}) {
  const keyHandler = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') { e.preventDefault(); onCommit?.(); }
    if (e.key === 'Escape') { e.preventDefault(); onCancel?.(); }
  };
  const inputCls = `input input-bordered input-${size} text-[12px] w-full ${invalid ? 'input-error outline outline-1 outline-error' : ''}`;

  switch (field.type) {
    case 'boolean':
      return (
        <input
          type="checkbox"
          className="toggle toggle-xs toggle-primary"
          autoFocus={autoFocus}
          checked={!!value}
          onKeyDown={keyHandler}
          onChange={(e) => onChange(e.target.checked)}
        />
      );
    case 'select':
      return (
        <select
          autoFocus={autoFocus}
          value={(value as string) ?? ''}
          onKeyDown={keyHandler}
          onChange={(e) => onChange(e.target.value || null)}
          className={`select select-bordered select-${size} text-[12px] w-full ${invalid ? 'select-error outline outline-1 outline-error' : ''}`}
        >
          <option value="">—</option>
          {(field.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
      );
    case 'number':
    case 'price':
      return (
        <input
          type="number"
          autoFocus={autoFocus}
          step={field.type === 'price' ? 0.01 : field.settings?.step as number | undefined}
          value={value === undefined || value === null || value === '' ? '' : String(value)}
          onKeyDown={keyHandler}
          onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
          className={`${inputCls} tabular-nums`}
        />
      );
    case 'date':
      return (
        <input
          type="date"
          autoFocus={autoFocus}
          value={(value as string) ?? ''}
          onKeyDown={keyHandler}
          onChange={(e) => onChange(e.target.value || null)}
          className={inputCls}
        />
      );
    default: // text, email, url, phone, sku
      return (
        <input
          autoFocus={autoFocus}
          type={field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'}
          value={(value as string) ?? ''}
          onKeyDown={keyHandler}
          onChange={(e) => onChange(e.target.value)}
          className={`${inputCls} ${field.type === 'sku' ? 'font-mono uppercase' : ''}`}
        />
      );
  }
}

/** Cell that turns into an inline editor on double-click (editable scalar columns only). */
function EditableCell({ siteId, field, record, onSave }: {
  siteId: string;
  field: CollectionField;
  record: CollectionRecord;
  onSave: (value: unknown) => Promise<void>;
}) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<unknown>(undefined);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const wrapRef = useRef<HTMLDivElement>(null);

  // Close on outside click while editing (without saving)
  useEffect(() => {
    if (!editing) return;
    const onDown = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setEditing(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [editing]);

  if (!isEditableScalar(field.type)) return <TypedCell siteId={siteId} field={field} record={record} />;

  const start = () => {
    setDraft(record.data?.[field.key] ?? (field.type === 'boolean' ? false : ''));
    setErr(null);
    setEditing(true);
  };

  const commit = async (value?: unknown) => {
    const v = value !== undefined ? value : draft;
    setSaving(true);
    setErr(null);
    try {
      await onSave(v === '' ? null : v);
      setEditing(false);
    } catch (e: any) {
      setErr(validationErrors(e)[`data.${field.key}`] ?? apiErr(e));
    } finally {
      setSaving(false);
    }
  };

  if (!editing) {
    return (
      <div onDoubleClick={start} title="Double-click to edit" className="cursor-cell min-h-[1.25rem]">
        <TypedCell siteId={siteId} field={field} record={record} />
      </div>
    );
  }

  return (
    <div ref={wrapRef} className="flex items-center gap-1.5 min-w-[8rem]" title={err ?? undefined}>
      <ScalarValueInput
        field={field}
        value={draft}
        onChange={field.type === 'boolean' ? (v) => { setDraft(v); void commit(v); } : setDraft}
        onCommit={() => void commit()}
        onCancel={() => setEditing(false)}
        autoFocus
        invalid={!!err}
      />
      {saving && <Loader2 size={12} className="animate-spin text-base-content/40 shrink-0" />}
    </div>
  );
}

export default function CollectionRecords() {
  const { siteId = '', collectionId = '' } = useParams();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { toast } = useToast();

  const [statusTab, setStatusTab] = useState<StatusTab>('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [sort, setSort] = useState('updated_at');
  const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [deleteTarget, setDeleteTarget] = useState<CollectionRecord | null>(null);
  const [forceDelete, setForceDelete] = useState<{ record: CollectionRecord; count: number } | null>(null);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [forceBulk, setForceBulk] = useState<{ ids: string[]; titles: string[] } | null>(null);
  const [setFieldOpen, setSetFieldOpen] = useState(false);
  const [setFieldKey, setSetFieldKey] = useState('');
  const [setFieldValue, setSetFieldValue] = useState<unknown>('');

  // Debounce search
  const searchTimeout = useMemo(() => {
    let timeout: ReturnType<typeof setTimeout>;
    return (value: string) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => { setDebouncedSearch(value); setPage(1); }, 300);
    };
  }, []);

  const { data: collection, isLoading: collectionLoading, error: collectionError } = useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collections.get(siteId, collectionId).then((r) => r.data.data),
  });

  const listFields = useMemo(
    () => (collection?.schema?.fields ?? []).filter((f) => f.show_in_list && f.key !== collection?.schema?.title_field),
    [collection],
  );

  const queryKey = ['collection-records', siteId, collectionId, statusTab, debouncedSearch, sort, direction, page, perPage];
  const { data: result, isLoading, error, isFetching } = useQuery<{ data: CollectionRecord[]; meta: RecordsMeta }>({
    queryKey,
    queryFn: () => {
      const params: Record<string, unknown> = { sort, direction, page, per_page: perPage };
      if (statusTab) params.status = statusTab;
      if (debouncedSearch.trim()) params.q = debouncedSearch.trim();
      return collectionRecords.list(siteId, collectionId, params).then((r) => r.data);
    },
    placeholderData: keepPreviousData,
    enabled: !!collection,
  });

  const meta = result?.meta;

  // Hierarchy (S3): tree-order the page with depth indents when the whole
  // collection fits on one unfiltered page; any filter/sort/paging falls
  // back to the flat list (a partial tree would mislead).
  const hierarchyKey = (collection?.settings as any)?.hierarchy_field ?? null;
  const treeDepths = useMemo(() => new Map<string, number>(), [result]);
  const records = useMemo(() => {
    const rows = result?.data ?? [];
    const treeable = hierarchyKey && !debouncedSearch.trim() && !statusTab && (meta?.last_page ?? 1) === 1;
    if (!treeable) return rows;
    const byParent = new Map<string | null, typeof rows>();
    const ids = new Set(rows.map((r) => r.id));
    for (const r of rows) {
      const parent = (r as any).parent_id && ids.has((r as any).parent_id) ? (r as any).parent_id : null;
      byParent.set(parent, [...(byParent.get(parent) ?? []), r]);
    }
    const ordered: typeof rows = [];
    const walk = (parent: string | null, depth: number) => {
      if (depth > 6) return;
      for (const r of byParent.get(parent) ?? []) {
        treeDepths.set(r.id, depth);
        ordered.push(r);
        walk(r.id, depth + 1);
      }
    };
    walk(null, 0);
    return ordered.length === rows.length ? ordered : rows;
  }, [result, hierarchyKey, debouncedSearch, statusTab, meta, treeDepths]);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['collection-records', siteId, collectionId] });

  const deleteMutation = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => collectionRecords.delete(siteId, collectionId, id, force),
    onSuccess: () => {
      invalidate();
      setDeleteTarget(null);
      setForceDelete(null);
      toast({ type: 'success', message: 'Record deleted.' });
    },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const record = records.find((r) => r.id === vars.id) ?? deleteTarget;
        if (record) setForceDelete({ record, count: e.response.data?.usedOnCount ?? 0 });
        setDeleteTarget(null);
      } else {
        toast({ type: 'error', message: apiErr(e) });
        setDeleteTarget(null);
      }
    },
  });

  const bulkMutation = useMutation({
    mutationFn: (body:
      | { action: 'publish' | 'draft' | 'delete'; ids: string[]; force?: boolean }
      | { action: 'set_field'; ids: string[]; field: string; value: unknown }) =>
      collectionRecords.bulk(siteId, collectionId, body),
    onSuccess: (res, vars) => {
      invalidate();
      const { done = 0, skipped = [] } = res.data.data ?? {};
      const verb = vars.action === 'publish' ? 'Published' : vars.action === 'draft' ? 'Set to draft'
        : vars.action === 'set_field' ? 'Updated' : 'Deleted';
      if (vars.action === 'set_field') {
        setSetFieldOpen(false);
        if (skipped.length > 0) {
          toast({
            type: 'info',
            message: `${verb} ${done} record(s); ${skipped.length} skipped: ${skipped
              .map((s: { title: string; error?: string }) => s.error ? `${s.title} (${s.error})` : s.title)
              .slice(0, 5).join(', ')}${skipped.length > 5 ? '…' : ''}`,
          });
        } else {
          toast({ type: 'success', message: `${verb} ${done} record(s).` });
        }
        setSelectedIds([]);
        return;
      }
      if (skipped.length > 0 && vars.action === 'delete' && !vars.force) {
        // Records in use were skipped — offer an explicit force pass on just those.
        toast({ type: 'info', message: `${verb} ${done} record(s); ${skipped.length} skipped (in use).` });
        setForceBulk({
          ids: skipped.map((s: { id: string }) => s.id),
          titles: skipped.map((s: { title: string; usedOnCount: number }) => `${s.title} (${s.usedOnCount} usage${s.usedOnCount === 1 ? '' : 's'})`),
        });
      } else if (skipped.length > 0) {
        toast({ type: 'info', message: `${verb} ${done} record(s); skipped: ${skipped.map((s: { title: string }) => s.title).slice(0, 5).join(', ')}${skipped.length > 5 ? '…' : ''}` });
      } else {
        toast({ type: 'success', message: `${verb} ${done} record(s).` });
      }
      setSelectedIds([]);
      setBulkDeleteOpen(false);
    },
    onError: (e) => { toast({ type: 'error', message: apiErr(e) }); setBulkDeleteOpen(false); },
  });

  const duplicateMutation = useMutation({
    mutationFn: (id: string) => collectionRecords.duplicate(siteId, collectionId, id),
    onSuccess: (res) => {
      invalidate();
      toast({ type: 'success', message: 'Record duplicated as draft.' });
      navigate(`/sites/${siteId}/collections/${collectionId}/records/${res.data.data.id}/edit`);
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  /** Inline cell save — full data object with one key replaced; rejects with the axios error. */
  const saveCell = async (r: CollectionRecord, field: CollectionField, value: unknown) => {
    await collectionRecords.update(siteId, collectionId, r.id, rowPayloadWith(r, field.key, value));
    invalidate();
  };

  const editableFields = useMemo(
    () => (collection?.schema?.fields ?? []).filter((f) => isEditableScalar(f.type)),
    [collection],
  );

  const openSetField = () => {
    const first = editableFields[0];
    setSetFieldKey(first?.key ?? '');
    setSetFieldValue(first?.type === 'boolean' ? false : '');
    setSetFieldOpen(true);
  };

  const toggleSort = (key: string) => {
    if (sort === key) setDirection((d) => (d === 'asc' ? 'desc' : 'asc'));
    else { setSort(key); setDirection('asc'); }
    setPage(1);
  };

  const SortIcon = ({ col }: { col: string }) =>
    sort === col
      ? direction === 'asc' ? <ArrowUp size={11} /> : <ArrowDown size={11} />
      : <ArrowUpDown size={11} className="opacity-30" />;

  const allOnPageSelected = records.length > 0 && records.every((r) => selectedIds.includes(r.id));
  const toggleAll = () => {
    setSelectedIds(allOnPageSelected ? selectedIds.filter((id) => !records.some((r) => r.id === id)) : [...new Set([...selectedIds, ...records.map((r) => r.id)])]);
  };
  const toggleOne = (id: string) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  if (collectionLoading) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>;
  }
  if (collectionError || !collection) {
    return <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the collection.</div>;
  }

  const hasFilters = !!debouncedSearch || statusTab !== '';
  const isEmpty = records.length === 0 && !isLoading;

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/sites/${siteId}/collections`} className="btn btn-ghost btn-sm btn-square text-base-content/40">
          <ArrowLeft size={16} />
        </Link>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-bold text-base-content truncate">
            {collection.icon ? `${collection.icon} ` : ''}{collection.name}
          </h1>
          <p className="text-[13px] text-base-content/50">
            {meta?.total ?? collection.records_count} record{(meta?.total ?? collection.records_count) === 1 ? '' : 's'}
            {' · '}
            <Link to={`/sites/${siteId}/collections/${collectionId}/schema`} className="text-primary hover:underline">edit schema</Link>
          </p>
        </div>
        <a href={collectionRecords.exportUrl(siteId, collectionId)} className="btn btn-ghost btn-sm gap-1.5 text-[12px]" title="Download all records as CSV">
          <Download size={13} /> Export CSV
        </a>
        <Link to={`/sites/${siteId}/collections/${collectionId}/import`} className="btn btn-outline btn-sm gap-1.5 text-[12px]">
          <Upload size={13} /> Import
        </Link>
        <Link to={`/sites/${siteId}/collections/${collectionId}/records/new`} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          <Plus size={14} /> New record
        </Link>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-4">
        <div className="tabs tabs-boxed tabs-sm bg-base-300/20 p-0.5">
          {([['', 'All'], ['published', 'Published'], ['draft', 'Draft']] as [StatusTab, string][]).map(([val, label]) => (
            <button key={val}
              onClick={() => { setStatusTab(val); setPage(1); setSelectedIds([]); }}
              className={`tab tab-sm text-[12px] ${statusTab === val ? 'tab-active bg-base-100' : ''}`}>
              {label}
            </button>
          ))}
        </div>
        <label className="input input-bordered input-sm flex items-center gap-2 text-[12px] w-64">
          <Search className="h-3.5 w-3.5 text-base-content/30" />
          <input
            type="text"
            value={search}
            onChange={(e) => { setSearch(e.target.value); searchTimeout(e.target.value); }}
            placeholder="Search records…"
            className="grow bg-transparent"
          />
        </label>
        {isFetching && !isLoading && <Loader2 size={14} className="animate-spin text-base-content/30" />}
        <div className="flex-1" />
        <select value={perPage} onChange={(e) => { setPerPage(Number(e.target.value)); setPage(1); }}
          className="select select-bordered select-sm text-[12px]">
          {PER_PAGE_CHOICES.map((n) => <option key={n} value={n}>{n} / page</option>)}
        </select>
      </div>

      {/* Bulk bar */}
      {selectedIds.length > 0 && (
        <div className="flex items-center gap-2 border border-primary/30 bg-primary/10 rounded-box px-3 py-2 mb-3">
          <span className="text-[13px] text-base-content/80 font-medium">{selectedIds.length} selected</span>
          <div className="flex-1" />
          <button onClick={() => bulkMutation.mutate({ action: 'publish', ids: selectedIds })}
            disabled={bulkMutation.isPending} className="btn btn-ghost btn-xs gap-1 text-[12px]">
            <Eye size={12} /> Publish
          </button>
          <button onClick={() => bulkMutation.mutate({ action: 'draft', ids: selectedIds })}
            disabled={bulkMutation.isPending} className="btn btn-ghost btn-xs gap-1 text-[12px]">
            <EyeOff size={12} /> Draft
          </button>
          {editableFields.length > 0 && (
            <button onClick={openSetField}
              disabled={bulkMutation.isPending} className="btn btn-ghost btn-xs gap-1 text-[12px]">
              <ListPlus size={12} /> Set field…
            </button>
          )}
          <button onClick={() => setBulkDeleteOpen(true)}
            disabled={bulkMutation.isPending} className="btn btn-ghost btn-xs gap-1 text-[12px] text-error">
            <Trash2 size={12} /> Delete
          </button>
          {bulkMutation.isPending && <Loader2 size={13} className="animate-spin text-base-content/40" />}
        </div>
      )}

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {!!error && <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load records.</div>}

      {/* Empty states */}
      {isEmpty && !hasFilters && !error && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <Database className="h-10 w-10 text-base-content/15 mb-4" strokeWidth={1.5} />
          <h3 className="text-sm font-medium text-base-content/60 mb-1">No records yet</h3>
          <p className="text-[13px] text-base-content/35 mb-6 max-w-xs">Add your first {collection.name.toLowerCase()} record, or bring in a whole spreadsheet at once.</p>
          <div className="flex gap-2">
            <Link to={`/sites/${siteId}/collections/${collectionId}/records/new`} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
              <Plus size={13} /> Add a record
            </Link>
            <Link to={`/sites/${siteId}/collections/${collectionId}/import`} className="btn btn-outline btn-sm gap-1.5 text-[12px]">
              <Upload size={13} /> Import CSV / Excel
            </Link>
          </div>
        </div>
      )}
      {isEmpty && hasFilters && !error && (
        <div className="py-16 text-center text-[13px] text-base-content/30">No records match — adjust the search or status filter.</div>
      )}

      {/* Table */}
      {records.length > 0 && (
        <div className="overflow-x-auto rounded-box border border-base-300/40 bg-base-100">
          <table className="table table-sm">
            <thead>
              <tr className="border-b border-base-300/40">
                <th className="w-8">
                  <input type="checkbox" className="checkbox checkbox-xs" checked={allOnPageSelected} onChange={toggleAll} aria-label="Select all on page" />
                </th>
                <th>
                  <button onClick={() => toggleSort('title')} className="flex items-center gap-1 text-[11px] font-medium text-base-content/40 uppercase tracking-wider hover:text-base-content/70">
                    Title <SortIcon col="title" />
                  </button>
                </th>
                {listFields.map((f) => (
                  <th key={f.key}>
                    {isSortableType(f.type) ? (
                      <button onClick={() => toggleSort(`data.${f.key}`)} className="flex items-center gap-1 text-[11px] font-medium text-base-content/40 uppercase tracking-wider hover:text-base-content/70">
                        {f.label} <SortIcon col={`data.${f.key}`} />
                      </button>
                    ) : (
                      <span className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">{f.label}</span>
                    )}
                  </th>
                ))}
                <th>
                  <button onClick={() => toggleSort('status')} className="flex items-center gap-1 text-[11px] font-medium text-base-content/40 uppercase tracking-wider hover:text-base-content/70">
                    Status <SortIcon col="status" />
                  </button>
                </th>
                <th>
                  <button onClick={() => toggleSort('updated_at')} className="flex items-center gap-1 text-[11px] font-medium text-base-content/40 uppercase tracking-wider hover:text-base-content/70">
                    Updated <SortIcon col="updated_at" />
                  </button>
                </th>
                <th className="w-20" />
              </tr>
            </thead>
            <tbody>
              {records.map((r) => (
                <tr key={r.id} className="border-b border-base-300/20 hover:bg-base-300/10 transition-colors">
                  <td>
                    <input type="checkbox" className="checkbox checkbox-xs" checked={selectedIds.includes(r.id)} onChange={() => toggleOne(r.id)} aria-label={`Select ${r.title}`} />
                  </td>
                  <td>
                    <div style={{ paddingLeft: `${(treeDepths.get(r.id) ?? 0) * 18}px` }} className="flex items-baseline gap-1">
                      {(treeDepths.get(r.id) ?? 0) > 0 && <span className="text-base-content/25 text-[11px]">└</span>}
                      <div>
                        <Link to={`/sites/${siteId}/collections/${collectionId}/records/${r.id}/edit`} className="text-[13px] font-medium text-base-content hover:text-primary transition-colors">
                          {r.title || <em className="text-base-content/35">untitled</em>}
                        </Link>
                        <div className="text-[11px] text-base-content/30 font-mono">{r.slug}</div>
                      </div>
                    </div>
                  </td>
                  {listFields.map((f) => (
                    <td key={f.key} className="text-[13px] text-base-content/70">
                      <EditableCell siteId={siteId} field={f} record={r} onSave={(v) => saveCell(r, f, v)} />
                    </td>
                  ))}
                  <td>
                    <div className="flex items-center gap-1 flex-wrap">
                      <StatusBadge status={r.status} />
                      {r.publish_at && (
                        <span className="badge badge-outline badge-xs gap-0.5 text-[10px] text-info"
                          title={`Scheduled to publish ${new Date(r.publish_at).toLocaleString()}`}>
                          <CalendarClock size={9} /> scheduled
                        </span>
                      )}
                      {r.unpublish_at && (
                        <span className="badge badge-outline badge-xs gap-0.5 text-[10px] text-warning"
                          title={`Unpublishes ${new Date(r.unpublish_at).toLocaleString()}`}>
                          <TimerOff size={9} /> expires
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="text-[12px] text-base-content/40 whitespace-nowrap">
                    {r.updated_at ? new Date(r.updated_at).toLocaleDateString() : '—'}
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-0.5">
                      <Link to={`/sites/${siteId}/collections/${collectionId}/records/${r.id}/edit`}
                        className="btn btn-ghost btn-xs btn-square" title="Edit"><Pencil size={13} /></Link>
                      <button onClick={() => duplicateMutation.mutate(r.id)} disabled={duplicateMutation.isPending}
                        className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-primary" title="Duplicate">
                        {duplicateMutation.isPending && duplicateMutation.variables === r.id
                          ? <Loader2 size={13} className="animate-spin" /> : <Copy size={13} />}
                      </button>
                      <button onClick={() => setDeleteTarget(r)}
                        className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Delete"><Trash2 size={13} /></button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-4">
          <span className="text-[12px] text-base-content/40">
            Page {meta.current_page} of {meta.last_page} · {meta.total} records
          </span>
          <div className="join">
            <button className="join-item btn btn-sm text-[12px]" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>«</button>
            <button className="join-item btn btn-sm text-[12px] pointer-events-none">{meta.current_page}</button>
            <button className="join-item btn btn-sm text-[12px]" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>»</button>
          </div>
        </div>
      )}

      {/* Single delete */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete record"
        message={`Delete "${deleteTarget?.title || 'this record'}"?`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id })}
        onClose={() => setDeleteTarget(null)}
      />
      <ConfirmDialog
        open={!!forceDelete}
        title="Record is still in use"
        message={`"${forceDelete?.record.title}" is used in ${forceDelete?.count} place${forceDelete?.count === 1 ? '' : 's'} (pages or relations). Deleting it will leave gaps there. Delete anyway?`}
        confirmText="Force delete"
        variant="danger"
        onConfirm={() => forceDelete && deleteMutation.mutate({ id: forceDelete.record.id, force: true })}
        onClose={() => setForceDelete(null)}
      />

      {/* Bulk delete */}
      <ConfirmDialog
        open={bulkDeleteOpen}
        title="Delete selected records"
        message={`Delete ${selectedIds.length} record${selectedIds.length === 1 ? '' : 's'}? Records that are in use will be skipped (you'll be asked about them next).`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => bulkMutation.mutate({ action: 'delete', ids: selectedIds })}
        onClose={() => setBulkDeleteOpen(false)}
      />
      {/* Bulk set field */}
      <Modal open={setFieldOpen} onClose={() => setSetFieldOpen(false)} title={`Set a field on ${selectedIds.length} record${selectedIds.length === 1 ? '' : 's'}`}>
        {(() => {
          const field = editableFields.find((f) => f.key === setFieldKey) ?? null;
          return (
            <div className="space-y-4">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Field</label>
                <select
                  value={setFieldKey}
                  onChange={(e) => {
                    const next = editableFields.find((f) => f.key === e.target.value);
                    setSetFieldKey(e.target.value);
                    setSetFieldValue(next?.type === 'boolean' ? false : '');
                  }}
                  className="select select-bordered select-sm w-full text-[13px]"
                >
                  {editableFields.map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
                </select>
              </div>
              {field && (
                <div>
                  <label className="text-[11px] text-base-content/50 mb-1 block">New value</label>
                  <ScalarValueInput field={field} value={setFieldValue} onChange={setSetFieldValue} size="sm" />
                  <p className="text-[11px] text-base-content/30 mt-1">Applied to every selected record; unique conflicts are skipped.</p>
                </div>
              )}
              <div className="flex justify-end gap-2 pt-1">
                <button onClick={() => setSetFieldOpen(false)} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
                <button
                  onClick={() => field && bulkMutation.mutate({ action: 'set_field', ids: selectedIds, field: field.key, value: setFieldValue === '' ? null : setFieldValue })}
                  disabled={!field || bulkMutation.isPending}
                  className="btn btn-primary btn-sm gap-1.5 text-[12px]"
                >
                  {bulkMutation.isPending && <Loader2 size={13} className="animate-spin" />}
                  Apply
                </button>
              </div>
            </div>
          );
        })()}
      </Modal>

      <ConfirmDialog
        open={!!forceBulk}
        title="Some records are in use"
        message={`${forceBulk?.ids.length} record${forceBulk?.ids.length === 1 ? ' was' : 's were'} skipped because they're referenced elsewhere: ${forceBulk?.titles.slice(0, 6).join(', ')}${(forceBulk?.titles.length ?? 0) > 6 ? '…' : ''}. Force delete them too?`}
        confirmText="Force delete"
        variant="danger"
        onConfirm={() => {
          if (forceBulk) bulkMutation.mutate({ action: 'delete', ids: forceBulk.ids, force: true });
          setForceBulk(null);
        }}
        onClose={() => setForceBulk(null)}
      />
    </div>
  );
}
