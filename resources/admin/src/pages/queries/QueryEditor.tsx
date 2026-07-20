import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Loader2, Plus, Trash2, Code2, Eye, Globe } from 'lucide-react';
import {
  collections, savedQueries,
  type Collection, type SavedQuery, type SavedQueryCondition, type SavedQueryDefinition,
  type SavedQueryGroup, type SavedQueryParam,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import {
  emptyCondition, emptyGroup, fieldPathOptions, isGroup, operatorsForType, pruneGroup,
  GROUPABLE_TYPES, MAX_GROUP_DEPTH, MAX_METRICS, MAX_SORT_KEYS, METRIC_FNS, NUMERIC_TYPES,
  type FieldPathOption,
} from '@/lib/queryBuilder';

/**
 * Track G-Q3 — the saved-query editor. Visual mode composes a validated
 * Simple-mode definition (filters/sort/limit/aggregate) with live preview
 * and the server's plain-language sentence; SQL mode is a guarded editor
 * over the per-site col_/rel_ views with the Show-as-SQL bridge.
 */
export default function QueryEditor() {
  const { siteId = '', queryId } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isNew = !queryId;

  const { data: existing, isLoading: loadingExisting } = useQuery<SavedQuery>({
    queryKey: ['saved-query', siteId, queryId],
    queryFn: () => savedQueries.get(siteId, queryId!).then((r) => r.data.data),
    enabled: !isNew,
  });
  const { data: allCollections = [] } = useQuery<Collection[]>({
    queryKey: ['collections', siteId],
    queryFn: () => collections.list(siteId).then((r) => r.data.data),
  });

  // ── Editable state ──
  const [name, setName] = useState('');
  const [mode, setMode] = useState<'simple' | 'sql'>('simple');
  const [collectionId, setCollectionId] = useState('');
  const [filters, setFilters] = useState<SavedQueryGroup | null>(null);
  const [sort, setSort] = useState<{ field: string; direction: 'asc' | 'desc' }[]>([]);
  const [limit, setLimit] = useState(100);
  const [aggregateOn, setAggregateOn] = useState(false);
  const [groupBy, setGroupBy] = useState('');
  const [metrics, setMetrics] = useState<{ fn: string; field?: string }[]>([{ fn: 'count' }]);
  const [sql, setSql] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [params, setParams] = useState<SavedQueryParam[]>([]);
  const [serverError, setServerError] = useState('');
  const [loadedId, setLoadedId] = useState<string | null>(null);

  useEffect(() => {
    if (!existing || existing.id === loadedId) return;
    setLoadedId(existing.id);
    setName(existing.name);
    setMode(existing.mode);
    setSql(existing.sql ?? '');
    setIsPublic(existing.is_public);
    setParams(existing.public_params ?? []);
    const def = existing.definition as SavedQueryDefinition;
    if (def?.collection_id) {
      setCollectionId(def.collection_id);
      setFilters((def.filters as SavedQueryGroup) ?? null);
      setSort(def.sort ?? []);
      setLimit(def.limit ?? 100);
      if (def.aggregate?.metrics?.length) {
        setAggregateOn(true);
        setGroupBy(def.aggregate.group_by ?? '');
        setMetrics(def.aggregate.metrics);
      }
    }
  }, [existing, loadedId]);

  const collection = allCollections.find((c) => c.id === collectionId) ?? null;
  const paths = useMemo(
    () => (collection ? fieldPathOptions(collection, allCollections) : []),
    [collection, allCollections],
  );
  const localFields = collection?.schema?.fields ?? [];

  const definition = useMemo((): SavedQueryDefinition | null => {
    if (!collectionId) return null;
    const def: SavedQueryDefinition = { collection_id: collectionId, limit };
    const pruned = filters ? pruneGroup(filters) : null;
    if (pruned) def.filters = pruned;
    const cleanSort = sort.filter((s) => s.field);
    if (cleanSort.length) def.sort = cleanSort;
    if (aggregateOn) {
      def.aggregate = {
        group_by: groupBy || null,
        metrics: metrics
          .filter((m) => m.fn === 'count' || m.field)
          .map((m) => (m.fn === 'count' ? { fn: 'count' as const } : { fn: m.fn as any, field: m.field })),
      };
    }
    return def;
  }, [collectionId, filters, sort, limit, aggregateOn, groupBy, metrics]);

  // ── Live preview (debounced) ──
  const [preview, setPreview] = useState<any>(null);
  const [previewError, setPreviewError] = useState('');
  const previewTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const previewBody = mode === 'simple'
    ? (definition ? { mode: 'simple', definition, public_params: params } : null)
    : (sql.trim() ? { mode: 'sql', sql } : null);
  const previewKey = JSON.stringify(previewBody);

  useEffect(() => {
    if (!previewBody) { setPreview(null); setPreviewError(''); return; }
    clearTimeout(previewTimer.current);
    previewTimer.current = setTimeout(() => {
      savedQueries.preview(siteId, previewBody)
        .then((r) => { setPreview(r.data.data); setPreviewError(''); })
        .catch((e) => {
          setPreview(null);
          const errs = e?.response?.data?.errors;
          setPreviewError(errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message ?? 'Preview failed.'));
        });
    }, 700);
    return () => clearTimeout(previewTimer.current);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [previewKey, siteId]);

  const saveMutation = useMutation({
    mutationFn: () => {
      const body: Record<string, unknown> = { name, mode, is_public: isPublic, public_params: params };
      if (mode === 'simple') body.definition = definition;
      else body.sql = sql;
      return isNew ? savedQueries.create(siteId, body) : savedQueries.update(siteId, queryId!, body);
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['saved-queries', siteId] });
      toast({ type: 'success', message: 'Query saved.' });
      setServerError('');
      if (isNew) navigate(`/sites/${siteId}/queries/${res.data.data.id}/edit`, { replace: true });
    },
    onError: (e: any) => {
      const errs = e?.response?.data?.errors;
      setServerError(errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message ?? 'Save failed.'));
    },
  });

  const canSave = name.trim().length > 0
    && (mode === 'sql' ? sql.trim().length > 0 : !!definition);

  if (!isNew && loadingExisting) {
    return <div className="flex justify-center py-20"><Loader2 className="animate-spin text-base-content/30" /></div>;
  }

  return (
    <div className="max-w-6xl mx-auto">
      <div className="flex items-center justify-between mb-5">
        <div className="flex items-center gap-3">
          <Link to={`/sites/${siteId}/queries`} className="btn btn-ghost btn-sm btn-square"><ArrowLeft size={16} /></Link>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Query name (e.g. Products under €500)"
            className="input input-bordered input-sm w-96 text-[13px] font-medium"
          />
        </div>
        <div className="flex items-center gap-2">
          <label className="flex items-center gap-1.5 text-[12px] text-base-content/60 cursor-pointer" title="Expose on the public read-only API">
            <input type="checkbox" className="toggle toggle-xs" checked={isPublic} onChange={(e) => setIsPublic(e.target.checked)} />
            <Globe size={12} /> Public API
          </label>
          <button onClick={() => saveMutation.mutate()} disabled={!canSave || saveMutation.isPending} className="btn btn-primary btn-sm text-[12px]">
            {saveMutation.isPending && <Loader2 size={13} className="animate-spin" />} Save
          </button>
        </div>
      </div>

      {serverError && <div className="alert alert-error text-[12px] py-2 mb-4">{serverError}</div>}

      <div className="tabs tabs-boxed w-fit mb-4 bg-base-200/60">
        <button className={`tab tab-sm text-[12px] ${mode === 'simple' ? 'tab-active' : ''}`} onClick={() => setMode('simple')}>
          <Eye size={13} className="mr-1" /> Visual
        </button>
        <button className={`tab tab-sm text-[12px] ${mode === 'sql' ? 'tab-active' : ''}`} onClick={() => setMode('sql')}>
          <Code2 size={13} className="mr-1" /> SQL
        </button>
      </div>

      <div className="grid grid-cols-12 gap-5">
        {/* ── Left: builder / editor ── */}
        <div className="col-span-12 lg:col-span-7 space-y-4">
          {mode === 'simple' ? (
            <>
              <div className="border border-base-300/40 rounded-box bg-base-100 p-4 space-y-3">
                <div>
                  <label className="text-[11px] text-base-content/50 mb-1 block">Collection</label>
                  <select value={collectionId} onChange={(e) => { setCollectionId(e.target.value); setFilters(null); setSort([]); setGroupBy(''); }}
                    className="select select-bordered select-sm w-full text-[13px]">
                    <option value="">— pick a collection —</option>
                    {allCollections.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>
                {collection && (
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-[11px] text-base-content/50 mb-1 block">Limit (1–500)</label>
                      <input type="number" min={1} max={500} value={limit}
                        onChange={(e) => setLimit(Math.max(1, Math.min(500, Number(e.target.value) || 1)))}
                        className="input input-bordered input-sm w-full text-[13px]" />
                    </div>
                  </div>
                )}
              </div>

              {collection && (
                <>
                  <div className="border border-base-300/40 rounded-box bg-base-100 p-4">
                    <div className="flex items-center justify-between mb-2">
                      <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Filters</h2>
                      {!filters && (
                        <button onClick={() => setFilters(emptyGroup())} className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary">
                          <Plus size={13} /> Add filters
                        </button>
                      )}
                    </div>
                    {filters
                      ? <GroupEditor group={filters} depth={1} paths={paths} onChange={setFilters} onRemove={() => setFilters(null)} />
                      : <p className="text-[12px] text-base-content/35">No filters — every published record matches.</p>}
                  </div>

                  <div className="border border-base-300/40 rounded-box bg-base-100 p-4">
                    <div className="flex items-center justify-between mb-2">
                      <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Sort</h2>
                      {sort.length < MAX_SORT_KEYS && (
                        <button onClick={() => setSort([...sort, { field: '', direction: 'desc' }])} className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary">
                          <Plus size={13} /> Add sort key
                        </button>
                      )}
                    </div>
                    {sort.length === 0 && <p className="text-[12px] text-base-content/35">Newest first (default).</p>}
                    {sort.map((s, i) => (
                      <div key={i} className="flex items-center gap-2 mb-2">
                        <select value={s.field} onChange={(e) => setSort(sort.map((x, j) => j === i ? { ...x, field: e.target.value } : x))}
                          className="select select-bordered select-sm flex-1 text-[12px]">
                          <option value="">— field —</option>
                          {localFields.filter((f) => f.type !== 'relation').map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
                        </select>
                        <select value={s.direction} onChange={(e) => setSort(sort.map((x, j) => j === i ? { ...x, direction: e.target.value as 'asc' | 'desc' } : x))}
                          className="select select-bordered select-sm text-[12px]">
                          <option value="asc">ascending</option>
                          <option value="desc">descending</option>
                        </select>
                        <button onClick={() => setSort(sort.filter((_, j) => j !== i))} className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={12} /></button>
                      </div>
                    ))}
                  </div>

                  <div className="border border-base-300/40 rounded-box bg-base-100 p-4">
                    <label className="flex items-center gap-2 text-[12px] font-medium text-base-content/70 uppercase tracking-wider cursor-pointer">
                      <input type="checkbox" className="toggle toggle-xs" checked={aggregateOn} onChange={(e) => setAggregateOn(e.target.checked)} />
                      Aggregate (counts &amp; sums instead of records)
                    </label>
                    {aggregateOn && (
                      <div className="mt-3 space-y-2">
                        <div>
                          <label className="text-[11px] text-base-content/50 mb-1 block">Group by</label>
                          <select value={groupBy} onChange={(e) => setGroupBy(e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
                            <option value="">— whole collection (one row) —</option>
                            {localFields.filter((f) => GROUPABLE_TYPES.includes(f.type)).map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
                          </select>
                        </div>
                        <label className="text-[11px] text-base-content/50 block">Metrics (max {MAX_METRICS})</label>
                        {metrics.map((m, i) => (
                          <div key={i} className="flex items-center gap-2">
                            <select value={m.fn} onChange={(e) => setMetrics(metrics.map((x, j) => j === i ? { fn: e.target.value, field: e.target.value === 'count' ? undefined : x.field } : x))}
                              className="select select-bordered select-sm text-[12px]">
                              {METRIC_FNS.map((fn) => <option key={fn} value={fn}>{fn}</option>)}
                            </select>
                            {m.fn !== 'count' && (
                              <select value={m.field ?? ''} onChange={(e) => setMetrics(metrics.map((x, j) => j === i ? { ...x, field: e.target.value } : x))}
                                className="select select-bordered select-sm flex-1 text-[12px]">
                                <option value="">— numeric field —</option>
                                {localFields.filter((f) => NUMERIC_TYPES.includes(f.type)).map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
                              </select>
                            )}
                            <button onClick={() => setMetrics(metrics.filter((_, j) => j !== i))} disabled={metrics.length === 1}
                              className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={12} /></button>
                          </div>
                        ))}
                        {metrics.length < MAX_METRICS && (
                          <button onClick={() => setMetrics([...metrics, { fn: 'count' }])} className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary">
                            <Plus size={13} /> Add metric
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                </>
              )}
            </>
          ) : (
            <div className="border border-base-300/40 rounded-box bg-base-100 p-4 space-y-2">
              <label className="text-[11px] text-base-content/50 block">
                SELECT-only, over this site's views (<code className="text-[10px]">col_&lt;collection&gt;</code>, <code className="text-[10px]">rel_&lt;collection&gt;_&lt;field&gt;</code>).
                Runs under a restricted read-only role with a 3s timeout and an automatic row cap.
              </label>
              <textarea value={sql} onChange={(e) => setSql(e.target.value)} rows={12} spellCheck={false}
                placeholder={'SELECT record_title, price\nFROM col_products\nWHERE price < 500\nORDER BY price DESC'}
                className="textarea textarea-bordered w-full font-mono text-[12px] leading-relaxed" />
              <p className="text-[11px] text-base-content/35">
                Tip: build in Visual mode first, then copy its SQL from the preview panel — it targets the same views.
              </p>
            </div>
          )}

          {/* Public params (both modes) */}
          <div className="border border-base-300/40 rounded-box bg-base-100 p-4">
            <div className="flex items-center justify-between mb-2">
              <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Public parameters</h2>
              <button onClick={() => setParams([...params, { key: '', type: 'text', required: false, default: null }])}
                className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary"><Plus size={13} /> Add</button>
            </div>
            {params.length === 0 && <p className="text-[12px] text-base-content/35">None — the public endpoint accepts no request parameters.</p>}
            {params.map((p, i) => (
              <div key={i} className="flex items-center gap-2 mb-2">
                <input value={p.key} onChange={(e) => setParams(params.map((x, j) => j === i ? { ...x, key: e.target.value } : x))}
                  placeholder="key (a-z, _)" className="input input-bordered input-sm flex-1 text-[12px] font-mono" />
                <select value={p.type} onChange={(e) => setParams(params.map((x, j) => j === i ? { ...x, type: e.target.value as SavedQueryParam['type'] } : x))}
                  className="select select-bordered select-sm text-[12px]">
                  <option value="text">text</option><option value="number">number</option><option value="boolean">boolean</option>
                </select>
                <button onClick={() => setParams(params.filter((_, j) => j !== i))} className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={12} /></button>
              </div>
            ))}
          </div>
        </div>

        {/* ── Right: live preview ── */}
        <div className="col-span-12 lg:col-span-5">
          <div className="border border-base-300/40 rounded-box bg-base-100 p-4 sticky top-4 space-y-3">
            <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Preview</h2>
            {previewError && <div className="text-[12px] text-error whitespace-pre-wrap">{previewError}</div>}
            {!previewError && !preview && <p className="text-[12px] text-base-content/35">{mode === 'sql' ? 'Write a statement to preview it.' : 'Pick a collection to preview.'}</p>}
            {preview?.sentence && (
              <p className="text-[13px] italic text-base-content/70 border-l-2 border-primary/40 pl-3">{preview.sentence}</p>
            )}
            {preview?.result && <ResultPreview result={preview.result} />}
            {mode === 'simple' && preview?.as_sql && (
              <details className="mt-2">
                <summary className="text-[11px] text-base-content/40 cursor-pointer">Show as SQL</summary>
                <pre className="text-[11px] font-mono bg-base-200/60 rounded p-2 mt-1 overflow-x-auto whitespace-pre-wrap">{preview.as_sql}</pre>
              </details>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Recursive filter-group editor ──

function GroupEditor({ group, depth, paths, onChange, onRemove }: {
  group: SavedQueryGroup;
  depth: number;
  paths: FieldPathOption[];
  onChange: (g: SavedQueryGroup) => void;
  onRemove: () => void;
}) {
  const setChild = (i: number, child: SavedQueryCondition | SavedQueryGroup) =>
    onChange({ ...group, children: group.children.map((c, j) => (j === i ? child : c)) });
  const removeChild = (i: number) => {
    const children = group.children.filter((_, j) => j !== i);
    children.length === 0 ? onRemove() : onChange({ ...group, children });
  };

  return (
    <div className={depth > 1 ? 'border-l-2 border-base-300/50 pl-3 py-1' : ''}>
      <div className="flex items-center gap-2 mb-2">
        <select value={group.op} onChange={(e) => onChange({ ...group, op: e.target.value as 'and' | 'or' })}
          className="select select-bordered select-xs text-[11px] w-24">
          <option value="and">ALL of</option>
          <option value="or">ANY of</option>
        </select>
        <button onClick={() => onChange({ ...group, children: [...group.children, emptyCondition()] })}
          className="btn btn-ghost btn-xs gap-1 text-[11px] text-primary"><Plus size={11} /> condition</button>
        {depth < MAX_GROUP_DEPTH && (
          <button onClick={() => onChange({ ...group, children: [...group.children, emptyGroup()] })}
            className="btn btn-ghost btn-xs gap-1 text-[11px] text-primary"><Plus size={11} /> group</button>
        )}
        {depth > 1 && <button onClick={onRemove} className="btn btn-ghost btn-xs btn-square text-base-content/40 ml-auto"><Trash2 size={11} /></button>}
      </div>
      <div className="space-y-2">
        {group.children.map((child, i) =>
          isGroup(child) ? (
            <GroupEditor key={i} group={child} depth={depth + 1} paths={paths}
              onChange={(g) => setChild(i, g)} onRemove={() => removeChild(i)} />
          ) : (
            <ConditionRow key={i} condition={child} paths={paths}
              onChange={(c) => setChild(i, c)} onRemove={() => removeChild(i)} />
          ),
        )}
      </div>
    </div>
  );
}

function ConditionRow({ condition, paths, onChange, onRemove }: {
  condition: SavedQueryCondition;
  paths: FieldPathOption[];
  onChange: (c: SavedQueryCondition) => void;
  onRemove: () => void;
}) {
  const meta = paths.find((p) => p.path === condition.field);
  const operators = meta ? operatorsForType(meta.type) : [];
  const noValue = ['is_empty', 'not_empty'].includes(condition.operator);
  const listValue = ['in', 'not_in', 'has_any'].includes(condition.operator);
  const between = condition.operator === 'between';

  const setField = (path: string) => {
    const newMeta = paths.find((p) => p.path === path);
    const ops = newMeta ? operatorsForType(newMeta.type) : [];
    const operator = ops.some((o) => o.value === condition.operator) ? condition.operator : (ops[0]?.value ?? 'eq');
    onChange({ field: path, operator, value: '' });
  };

  return (
    <div className="flex items-center gap-2 flex-wrap">
      <select value={condition.field} onChange={(e) => setField(e.target.value)}
        className="select select-bordered select-sm text-[12px] min-w-[10rem]">
        <option value="">— field —</option>
        {paths.map((p) => <option key={p.path} value={p.path}>{p.label}</option>)}
      </select>
      {meta && (
        <select value={condition.operator} onChange={(e) => onChange({ ...condition, operator: e.target.value, value: '' })}
          className="select select-bordered select-sm text-[12px]">
          {operators.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      )}
      {meta && !noValue && (
        between ? (
          <>
            <ValueInput meta={meta} value={Array.isArray(condition.value) ? condition.value[0] : ''} onChange={(v) => onChange({ ...condition, value: [v, Array.isArray(condition.value) ? condition.value[1] : ''] })} />
            <span className="text-[11px] text-base-content/40">and</span>
            <ValueInput meta={meta} value={Array.isArray(condition.value) ? condition.value[1] : ''} onChange={(v) => onChange({ ...condition, value: [Array.isArray(condition.value) ? condition.value[0] : '', v] })} />
          </>
        ) : listValue ? (
          meta.options?.length ? (
            <select multiple value={Array.isArray(condition.value) ? condition.value.map(String) : []}
              onChange={(e) => onChange({ ...condition, value: Array.from(e.target.selectedOptions).map((o) => o.value) })}
              className="select select-bordered select-sm text-[12px] min-h-[4rem]">
              {meta.options.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
          ) : (
            <input value={Array.isArray(condition.value) ? condition.value.join(', ') : String(condition.value ?? '')}
              onChange={(e) => onChange({ ...condition, value: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })}
              placeholder="comma, separated, values" className="input input-bordered input-sm flex-1 text-[12px]" />
          )
        ) : (
          <ValueInput meta={meta} value={condition.value} onChange={(v) => onChange({ ...condition, value: v })} />
        )
      )}
      <button onClick={onRemove} className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={12} /></button>
    </div>
  );
}

function ValueInput({ meta, value, onChange }: { meta: FieldPathOption; value: unknown; onChange: (v: unknown) => void }) {
  if (meta.type === 'boolean') {
    return (
      <select value={String(value ?? 'true')} onChange={(e) => onChange(e.target.value === 'true')}
        className="select select-bordered select-sm text-[12px]">
        <option value="true">yes</option><option value="false">no</option>
      </select>
    );
  }
  if (meta.type === 'select' && meta.options?.length) {
    return (
      <select value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}
        className="select select-bordered select-sm text-[12px]">
        <option value="">— value —</option>
        {meta.options.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
    );
  }
  const isNumber = NUMERIC_TYPES.includes(meta.type);
  const isDate = meta.type === 'date';
  return (
    <input
      type={isNumber ? 'number' : isDate ? 'date' : 'text'}
      value={String(value ?? '')}
      onChange={(e) => onChange(isNumber ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value)}
      className="input input-bordered input-sm text-[12px] w-36"
    />
  );
}

function ResultPreview({ result }: { result: any }) {
  if (result.type === 'value') {
    return <div className="text-2xl font-semibold tabular-nums">{String(result.value ?? '—')}</div>;
  }
  if (result.type === 'plan') {
    return <pre className="text-[11px] font-mono bg-base-200/60 rounded p-2 overflow-x-auto whitespace-pre-wrap">{result.text}</pre>;
  }
  if (result.type === 'table') {
    const rows: Record<string, unknown>[] = result.rows ?? [];
    if (rows.length === 0) return <p className="text-[12px] text-base-content/35">No groups.</p>;
    const cols = Object.keys(rows[0]);
    return (
      <div className="overflow-x-auto">
        <table className="table table-xs">
          <thead><tr>{cols.map((c) => <th key={c} className="text-[10px]">{c}</th>)}</tr></thead>
          <tbody>{rows.slice(0, 10).map((r, i) => (
            <tr key={i}>{cols.map((c) => <td key={c} className="text-[11px] tabular-nums">{String(r[c] ?? '—')}</td>)}</tr>
          ))}</tbody>
        </table>
      </div>
    );
  }
  const rows: { title?: string; slug?: string; record_title?: string }[] = result.rows ?? [];
  return (
    <div>
      <p className="text-[11px] text-base-content/40 mb-1">{result.total ?? rows.length} matching · first {Math.min(rows.length, 10)}</p>
      <ul className="text-[12px] space-y-0.5">
        {rows.slice(0, 10).map((r: any, i: number) => (
          <li key={i} className="truncate border-b border-base-300/20 py-0.5">
            {r.title ?? r.record_title ?? Object.values(r)[0] ?? '—'}
          </li>
        ))}
      </ul>
    </div>
  );
}
