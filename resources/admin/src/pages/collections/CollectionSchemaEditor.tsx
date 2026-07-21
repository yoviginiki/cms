import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import {
  ArrowLeft, Plus, Trash2, Loader2, GripVertical, AlertTriangle, X, Lock, Unlock,
  ChevronUp, ChevronDown, Save, Table2, Zap, RefreshCw,
} from 'lucide-react';
import {
  DndContext, PointerSensor, useSensor, useSensors, closestCenter, type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  collections,
  type Collection, type CollectionField, type CollectionFieldType,
  type CollectionPivotField, type CollectionPivotFieldType,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { Modal, TierPicker, apiErr, validationErrors, TIER_GUIDANCE } from './shared';
import {
  FIELD_TYPE_META, FIELD_TYPE_GROUPS, PIVOT_FIELD_TYPES,
  flagDisabledReason, keyFromLabel, fieldKeyError, isTitleCandidate, settingsForType, supportsDefault,
  type FieldFlag,
} from '@/lib/collectionFieldTypes';

// ─────────────────────────────────────────────────────────────────────────────
// Schema editor — define a collection's fields, title/slug source, name & tier.
// Field keys are locked once persisted (data lives under them); an explicit
// "unlock" affordance with a warning allows renames when the user insists.
// ─────────────────────────────────────────────────────────────────────────────

const FLAG_LABELS: { flag: FieldFlag; label: string; hint: string }[] = [
  { flag: 'required', label: 'Required', hint: 'Records can’t be saved without a value' },
  { flag: 'unique', label: 'Unique', hint: 'No two records may share the same value' },
  { flag: 'searchable', label: 'Searchable', hint: 'Included in full-text search' },
  { flag: 'facetable', label: 'Facetable', hint: 'Usable as a filter facet on the public site' },
  { flag: 'show_in_list', label: 'Show in list', hint: 'Shown as a column in the records table' },
];

export default function CollectionSchemaEditor() {
  const { siteId = '', collectionId = '' } = useParams();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: collection, isLoading, error } = useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collections.get(siteId, collectionId).then((r) => r.data.data),
  });

  // Target options for relation fields
  const { data: allCollections = [] } = useQuery<Collection[]>({
    queryKey: ['collections', siteId],
    queryFn: () => collections.list(siteId).then((r) => r.data.data),
  });

  // ── Local editable copy ──
  const [name, setName] = useState('');
  const [icon, setIcon] = useState('');
  const [tier, setTier] = useState<Collection['tier']>('static');
  const [fields, setFields] = useState<CollectionField[]>([]);
  const [titleField, setTitleField] = useState('');
  const [slugSource, setSlugSource] = useState('');
  const [savedKeys, setSavedKeys] = useState<string[]>([]); // keys persisted server-side → locked
  const [unlockedKeys, setUnlockedKeys] = useState<string[]>([]);
  const [selectedIdx, setSelectedIdx] = useState<number | null>(null);
  const [hierarchyField, setHierarchyField] = useState('');
  const [dirty, setDirty] = useState(false);
  const [warnings, setWarnings] = useState<string[]>([]);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [typePickerOpen, setTypePickerOpen] = useState(false);
  const [convertFieldKey, setConvertFieldKey] = useState<string | null>(null);
  // Data source (scheduled URL import)
  const [importUrl, setImportUrl] = useState('');
  const [importSchedule, setImportSchedule] = useState<'' | 'hourly' | 'daily'>('');
  const [importKey, setImportKey] = useState('');
  const [importStatus, setImportStatus] = useState<'draft' | 'published'>('draft');

  useEffect(() => {
    if (!collection) return;
    setName(collection.name);
    setIcon(collection.icon || '');
    setTier(collection.tier);
    setFields(collection.schema?.fields ?? []);
    setTitleField(collection.schema?.title_field ?? '');
    setSlugSource(collection.schema?.slug_source ?? '');
    setSavedKeys((collection.schema?.fields ?? []).map((f) => f.key));
    setUnlockedKeys([]);
    setHierarchyField((collection.settings as any)?.hierarchy_field ?? '');
    const s = (collection.settings ?? {}) as any;
    setImportUrl(s.import_url ?? '');
    setImportSchedule(s.import_schedule ?? '');
    setImportKey(s.import_key ?? '');
    setImportStatus(s.import_status ?? 'draft');
    setDirty(false);
  }, [collection]);

  const touch = () => setDirty(true);

  const updateField = (idx: number, patch: Partial<CollectionField>) => {
    setFields((prev) => prev.map((f, i) => (i === idx ? { ...f, ...patch } : f)));
    touch();
  };

  const addField = (type: CollectionFieldType) => {
    const base: CollectionField = {
      key: '',
      label: '',
      type,
      show_in_list: true,
      ...(type === 'select' || type === 'multi_select' ? { options: [] } : {}),
      ...(type === 'relation' ? { relation: { collection_id: '', mode: 'one' as const } } : {}),
      ...(type === 'computed' ? { computed: { fn: 'count' as const, collection_id: '', relation_key: '' } } : {}),
    };
    setFields((prev) => [...prev, base]);
    setSelectedIdx(fields.length);
    setTypePickerOpen(false);
    touch();
  };

  const removeField = (idx: number) => {
    const f = fields[idx];
    if (savedKeys.includes(f.key) && !confirm(`Remove field "${f.label || f.key}"? Its data will be dropped from all records on save.`)) return;
    setFields((prev) => prev.filter((_, i) => i !== idx));
    if (titleField === f.key) setTitleField('');
    if (slugSource === f.key) setSlugSource('');
    setSelectedIdx(null);
    touch();
  };

  const moveField = (idx: number, dir: -1 | 1) => {
    const to = idx + dir;
    if (to < 0 || to >= fields.length) return;
    setFields((prev) => arrayMove(prev, idx, to));
    setSelectedIdx(to);
    touch();
  };

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
  const onDragEnd = (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    const from = fields.findIndex((_, i) => `field-${i}` === active.id);
    const to = fields.findIndex((_, i) => `field-${i}` === over.id);
    if (from < 0 || to < 0) return;
    setFields((prev) => arrayMove(prev, from, to));
    setSelectedIdx(to);
    touch();
  };

  // ── Client-side validation ──
  const clientErrors = useMemo(() => {
    const errs: Record<number, string> = {};
    fields.forEach((f, i) => {
      const others = fields.filter((_, j) => j !== i).map((x) => x.key);
      const keyErr = fieldKeyError(f.key, others);
      if (keyErr) errs[i] = keyErr;
      else if (!f.label.trim()) errs[i] = 'Label is required';
      else if ((f.type === 'select' || f.type === 'multi_select') && (f.options ?? []).length === 0) errs[i] = 'Add at least one option';
      else if (f.type === 'relation' && !f.relation?.collection_id) errs[i] = 'Pick a target collection';
      else if (f.type === 'computed' && (!f.computed?.collection_id || !f.computed?.relation_key)) errs[i] = 'Pick a source collection and relation';
      else if (f.type === 'computed' && f.computed?.fn === 'sum' && !f.computed?.sum_field) errs[i] = 'Pick a numeric field to sum';
    });
    return errs;
  }, [fields]);

  const titleCandidates = fields.filter(isTitleCandidate);
  const titleMissing = fields.length > 0 && (!titleField || !titleCandidates.some((f) => f.key === titleField));

  const canSave = dirty && Object.keys(clientErrors).length === 0 && !titleMissing && name.trim().length > 0;

  const buildSettings = () => ({
    ...(collection?.settings ?? {}),
    hierarchy_field: hierarchyField || null,
    import_url: importUrl.trim() || null,
    import_schedule: importSchedule || null,
    import_key: importKey || null,
    import_status: importStatus,
  });

  const saveMutation = useMutation({
    mutationFn: () =>
      collections.update(siteId, collectionId, {
        name: name.trim(),
        icon: icon.trim() || undefined,
        tier,
        schema: {
          fields: fields.map((f) => cleanField(f)),
          title_field: titleField,
          slug_source: slugSource || titleField,
        },
        settings: buildSettings(),
      }),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['collection', siteId, collectionId] });
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      setWarnings(res.data.warnings ?? []);
      setServerErrors({});
      setDirty(false);
      setUnlockedKeys([]);
      toast({ type: 'success', message: 'Schema saved.' });
    },
    onError: (e: any) => {
      const errs = validationErrors(e);
      if (Object.keys(errs).length > 0) {
        setServerErrors(errs);
        toast({ type: 'error', message: 'Validation failed — check the highlighted fields.' });
      } else {
        toast({ type: 'error', message: apiErr(e) });
      }
    },
  });

  // Tier warning banner: one-click upgrade to the dynamic tier (server payload,
  // not local edits — the banner may be acted on with unsaved schema changes).
  const switchTierMutation = useMutation({
    mutationFn: () =>
      collections.update(siteId, collectionId, {
        tier: 'dynamic',
        name: collection!.name,
        icon: collection!.icon || undefined,
        schema: collection!.schema,
        settings: collection!.settings ?? {},
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection', siteId, collectionId] });
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      setTier('dynamic');
      toast({ type: 'success', message: 'Switched to the dynamic tier.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  // Text → select conversion
  const convertMutation = useMutation({
    mutationFn: (field: string) => collections.convert(siteId, collectionId, field, 'select'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection', siteId, collectionId] });
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      setConvertFieldKey(null);
      setSelectedIdx(null);
      toast({ type: 'success', message: 'Field converted to select.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const uniqueFieldKeys = fields.filter((f) => f.unique).map((f) => f.key);

  // Map "schema.fields.3.key" style server errors onto field indexes
  const serverErrorForField = (idx: number): string | null => {
    for (const [k, msg] of Object.entries(serverErrors)) {
      if (k.startsWith(`schema.fields.${idx}.`) || k === `schema.fields.${idx}`) return msg;
    }
    return null;
  };

  const selected = selectedIdx !== null ? fields[selectedIdx] : null;

  if (isLoading) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>;
  }
  if (error || !collection) {
    return <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the collection.</div>;
  }

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/sites/${siteId}/collections`} className="btn btn-ghost btn-sm btn-square text-base-content/40">
          <ArrowLeft size={16} />
        </Link>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-bold text-base-content truncate">
            {collection.icon ? `${collection.icon} ` : ''}{collection.name} — Schema
          </h1>
          <p className="text-[13px] text-base-content/50">Define fields, then add records</p>
        </div>
        <Link to={`/sites/${siteId}/collections/${collectionId}/records`} className="btn btn-ghost btn-sm gap-1.5 text-[12px]">
          <Table2 size={13} /> Records
        </Link>
        <button
          onClick={() => saveMutation.mutate()}
          disabled={!canSave || saveMutation.isPending}
          className="btn btn-primary btn-sm gap-1.5 text-[12px]"
        >
          {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
          Save schema
        </button>
      </div>

      {/* Tier warning — the collection outgrew the static tier */}
      {collection.tier_warning && collection.tier !== 'dynamic' && (
        <div className="border border-warning/40 bg-warning/10 rounded-box p-4 mb-5">
          <div className="flex items-center gap-2.5">
            <AlertTriangle className="h-4 w-4 text-warning shrink-0" />
            <p className="flex-1 text-[13px] text-base-content/80">{collection.tier_warning}</p>
            <button
              onClick={() => switchTierMutation.mutate()}
              disabled={switchTierMutation.isPending}
              className="btn btn-warning btn-sm gap-1.5 text-[12px]"
            >
              {switchTierMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Zap size={13} />}
              Switch to dynamic
            </button>
          </div>
        </div>
      )}

      {/* Warnings from the last save — describe data-affecting schema changes */}
      {warnings.length > 0 && (
        <div className="border border-warning/40 bg-warning/10 rounded-box p-4 mb-5">
          <div className="flex items-start gap-2.5">
            <AlertTriangle className="h-4 w-4 text-warning mt-0.5 shrink-0" />
            <div className="flex-1 text-[13px] text-base-content/80 space-y-1">
              <p className="font-medium text-warning">Schema change affected existing data</p>
              {warnings.map((w, i) => <p key={i}>{w}</p>)}
            </div>
            <button onClick={() => setWarnings([])} className="btn btn-ghost btn-xs btn-square"><X size={12} /></button>
          </div>
        </div>
      )}

      {/* Top-level server errors (name / title_field / non-field-indexed) */}
      {Object.entries(serverErrors).filter(([k]) => !k.startsWith('schema.fields.')).length > 0 && (
        <div className="border border-error/30 bg-error/10 rounded-box p-3 mb-5 text-[13px] text-error space-y-0.5">
          {Object.entries(serverErrors).filter(([k]) => !k.startsWith('schema.fields.')).map(([k, msg]) => (
            <p key={k}>{msg}</p>
          ))}
        </div>
      )}

      <div className="grid grid-cols-12 gap-5">
        {/* ── Left: collection settings + field list ── */}
        <div className="col-span-12 lg:col-span-7 space-y-5">
          {/* Collection basics */}
          <div className="border border-base-300/40 rounded-box bg-base-100 p-4 space-y-3">
            <div className="grid grid-cols-12 gap-3">
              <div className="col-span-8">
                <label className="text-[11px] text-base-content/50 mb-1 block">Name</label>
                <input value={name} onChange={(e) => { setName(e.target.value); touch(); }}
                  className={`input input-bordered input-sm w-full text-[13px] ${serverErrors.name ? 'input-error' : ''}`} />
                {serverErrors.name && <p className="text-[11px] text-error mt-1">{serverErrors.name}</p>}
              </div>
              <div className="col-span-4">
                <label className="text-[11px] text-base-content/50 mb-1 block">Icon</label>
                <input value={icon} onChange={(e) => { setIcon(e.target.value); touch(); }} maxLength={8}
                  placeholder="📦" className="input input-bordered input-sm w-full text-[13px]" />
              </div>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Tier</label>
              <TierPicker value={tier} onChange={(t) => { setTier(t); touch(); }} />
              {tier !== collection.tier && (
                <p className="text-[11px] text-warning mt-1.5">
                  Switching to {TIER_GUIDANCE[tier].title.toLowerCase()}: {TIER_GUIDANCE[tier].text}
                </p>
              )}
            </div>
            {/* Hierarchy (S3): pick a self-relation "parent" field to make this a tree */}
            <div>
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Hierarchy</label>
              {(() => {
                const eligible = fields.filter(
                  (f) => f.type === 'relation' && f.relation?.mode === 'one' && f.relation?.collection_id === collectionId,
                );
                if (eligible.length === 0) {
                  return (
                    <div className="flex items-center gap-2">
                      <p className="text-[12px] text-base-content/40">Flat collection.</p>
                      {!fields.some((f) => f.key === 'parent') && (
                        <button
                          type="button"
                          className="btn btn-ghost btn-xs text-[11px] text-primary"
                          onClick={() => {
                            setFields((prev) => [...prev, {
                              key: 'parent', label: 'Parent', type: 'relation' as const, show_in_list: true,
                              relation: { collection_id: collectionId, mode: 'one' as const },
                            }]);
                            setHierarchyField('parent');
                            touch();
                          }}
                        >
                          Make hierarchical (adds a Parent field)
                        </button>
                      )}
                    </div>
                  );
                }
                return (
                  <>
                    <select
                      value={hierarchyField}
                      onChange={(e) => { setHierarchyField(e.target.value); touch(); }}
                      className="select select-bordered select-sm w-full text-[13px]"
                    >
                      <option value="">Flat (no hierarchy)</option>
                      {eligible.map((f) => (
                        <option key={f.key} value={f.key}>Tree via “{f.label || f.key}”</option>
                      ))}
                    </select>
                    {serverErrors['settings.hierarchy_field'] && (
                      <p className="text-[11px] text-error mt-1">{serverErrors['settings.hierarchy_field']}</p>
                    )}
                    {hierarchyField && (
                      <p className="text-[11px] text-base-content/40 mt-1.5">
                        Records nest under their parent (max 6 levels, loops rejected). Published URLs follow the tree, e.g. /{collection.slug}/painting/oil/.
                      </p>
                    )}
                  </>
                );
              })()}
            </div>
          </div>

          {/* Field list */}
          <div className="border border-base-300/40 rounded-box bg-base-100">
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-base-300/30">
              <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Fields</h2>
              <button onClick={() => setTypePickerOpen(true)} className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary">
                <Plus size={13} /> Add field
              </button>
            </div>

            {fields.length === 0 && (
              <div className="py-12 text-center">
                <p className="text-[13px] text-base-content/35 mb-3">No fields yet — a collection needs at least one text field for record titles.</p>
                <button onClick={() => setTypePickerOpen(true)} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
                  <Plus size={14} /> Add your first field
                </button>
              </div>
            )}

            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
              <SortableContext items={fields.map((_, i) => `field-${i}`)} strategy={verticalListSortingStrategy}>
                <div className="divide-y divide-base-300/20">
                  {fields.map((f, i) => (
                    <SortableFieldRow
                      key={`field-${i}`}
                      id={`field-${i}`}
                      field={f}
                      selected={selectedIdx === i}
                      error={clientErrors[i] ?? serverErrorForField(i)}
                      isTitle={titleField === f.key && !!f.key}
                      onSelect={() => setSelectedIdx(selectedIdx === i ? null : i)}
                      onRemove={() => removeField(i)}
                      onMoveUp={i > 0 ? () => moveField(i, -1) : undefined}
                      onMoveDown={i < fields.length - 1 ? () => moveField(i, 1) : undefined}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          </div>

          {/* Title / slug source */}
          {fields.length > 0 && (
            <div className="border border-base-300/40 rounded-box bg-base-100 p-4 grid grid-cols-12 gap-3">
              <div className="col-span-6">
                <label className="text-[11px] text-base-content/50 mb-1 block">Title field</label>
                <select value={titleField} onChange={(e) => { setTitleField(e.target.value); touch(); }}
                  className={`select select-bordered select-sm w-full text-[13px] ${titleMissing ? 'select-error' : ''}`}>
                  <option value="">— pick a field —</option>
                  {titleCandidates.map((f) => (
                    <option key={f.key} value={f.key}>{f.label || f.key}</option>
                  ))}
                </select>
                <p className="text-[11px] text-base-content/35 mt-1">
                  {titleCandidates.length === 0
                    ? 'Add a text or SKU field first — one of them becomes the record title.'
                    : 'Which text/SKU field names each record.'}
                </p>
              </div>
              <div className="col-span-6">
                <label className="text-[11px] text-base-content/50 mb-1 block">Slug source</label>
                <select value={slugSource} onChange={(e) => { setSlugSource(e.target.value); touch(); }}
                  className="select select-bordered select-sm w-full text-[13px]">
                  <option value="">Same as title field</option>
                  {titleCandidates.map((f) => (
                    <option key={f.key} value={f.key}>{f.label || f.key}</option>
                  ))}
                </select>
                <p className="text-[11px] text-base-content/35 mt-1">Record URLs are generated from this field.</p>
              </div>
            </div>
          )}

          {/* Data source — scheduled URL import */}
          <div className="border border-base-300/40 rounded-box bg-base-100">
            <div className="flex items-center gap-2 px-4 py-2.5 border-b border-base-300/30">
              <RefreshCw size={13} className="text-base-content/40" />
              <h2 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Data source</h2>
              <span className="text-[11px] text-base-content/30">(optional — re-import records from a URL on a schedule)</span>
            </div>
            <div className="p-4 grid grid-cols-12 gap-3">
              <div className="col-span-12">
                <label className="text-[11px] text-base-content/50 mb-1 block">Import URL (CSV / Excel / JSON)</label>
                <input
                  value={importUrl}
                  onChange={(e) => { setImportUrl(e.target.value); touch(); }}
                  placeholder="https://example.com/products.csv"
                  className={`input input-bordered input-sm w-full text-[13px] font-mono ${serverErrors['settings.import_url'] ? 'input-error' : ''}`}
                />
                {serverErrors['settings.import_url'] && <p className="text-[11px] text-error mt-1">{serverErrors['settings.import_url']}</p>}
              </div>
              <div className="col-span-4">
                <label className="text-[11px] text-base-content/50 mb-1 block">Schedule</label>
                <select
                  value={importSchedule}
                  onChange={(e) => { setImportSchedule(e.target.value as '' | 'hourly' | 'daily'); touch(); }}
                  className="select select-bordered select-sm w-full text-[13px]"
                >
                  <option value="">Off (manual only)</option>
                  <option value="hourly">Hourly</option>
                  <option value="daily">Daily</option>
                </select>
              </div>
              <div className="col-span-4">
                <label className="text-[11px] text-base-content/50 mb-1 block">Match on (upsert key)</label>
                <select
                  value={importKey}
                  onChange={(e) => { setImportKey(e.target.value); touch(); }}
                  className={`select select-bordered select-sm w-full text-[13px] ${serverErrors['settings.import_key'] ? 'select-error' : ''}`}
                >
                  <option value="">— always create new —</option>
                  {uniqueFieldKeys.map((k) => <option key={k} value={k}>{fields.find((f) => f.key === k)?.label || k}</option>)}
                </select>
                {serverErrors['settings.import_key'] && <p className="text-[11px] text-error mt-1">{serverErrors['settings.import_key']}</p>}
              </div>
              <div className="col-span-4">
                <label className="text-[11px] text-base-content/50 mb-1 block">Imported records are</label>
                <select
                  value={importStatus}
                  onChange={(e) => { setImportStatus(e.target.value as 'draft' | 'published'); touch(); }}
                  className="select select-bordered select-sm w-full text-[13px]"
                >
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                </select>
              </div>
              {uniqueFieldKeys.length === 0 && (
                <p className="col-span-12 text-[11px] text-base-content/35 -mt-1">
                  Add a <em>unique</em> field to enable upserts — without one every scheduled run creates new records.
                </p>
              )}
            </div>
          </div>
        </div>

        {/* ── Right: settings panel for the selected field ── */}
        <div className="col-span-12 lg:col-span-5">
          {selected && selectedIdx !== null ? (
            <FieldSettingsPanel
              field={selected}
              idx={selectedIdx}
              locked={savedKeys.includes(selected.key) && !unlockedKeys.includes(selected.key)}
              onUnlock={() => {
                if (confirm('Renaming a key orphans existing data stored under the old key. Records keep the old values but the field will read empty until re-entered or migrated. Unlock anyway?')) {
                  setUnlockedKeys((prev) => [...prev, selected.key]);
                }
              }}
              allFields={fields}
              collectionsForRelation={allCollections.filter((c) => c.id !== collectionId)}
              currentCollection={collection}
              serverError={serverErrorForField(selectedIdx)}
              clientError={clientErrors[selectedIdx] ?? null}
              onChange={(patch) => updateField(selectedIdx, patch)}
              onClose={() => setSelectedIdx(null)}
              onRequestConvert={
                selected.type === 'text' && savedKeys.includes(selected.key)
                  ? () => setConvertFieldKey(selected.key)
                  : undefined
              }
            />
          ) : (
            <div className="border border-dashed border-base-300/40 rounded-box py-16 text-center text-[13px] text-base-content/30 sticky top-4">
              Select a field to edit its settings
            </div>
          )}
        </div>
      </div>

      {/* Type picker */}
      <Modal open={typePickerOpen} onClose={() => setTypePickerOpen(false)} title="Add a field" maxW="max-w-2xl">
        <div className="space-y-4">
          {FIELD_TYPE_GROUPS.map(({ group, types }) => (
            <div key={group}>
              <h4 className="text-[10px] font-medium text-base-content/30 uppercase tracking-wider mb-1.5">{group}</h4>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                {types.map((t) => {
                  const meta = FIELD_TYPE_META[t];
                  return (
                    <button key={t} onClick={() => addField(t)}
                      className="flex items-start gap-2.5 p-2.5 border border-base-300/40 rounded-box text-left hover:border-primary/50 hover:bg-primary/5 transition-colors">
                      <meta.icon size={15} className="text-base-content/40 mt-0.5 shrink-0" strokeWidth={1.5} />
                      <div className="min-w-0">
                        <div className="text-[13px] font-medium text-base-content">{meta.label}</div>
                        <div className="text-[11px] text-base-content/35 leading-snug">{meta.hint}</div>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      </Modal>

      {/* Convert text → select */}
      <ConvertToSelectModal
        siteId={siteId}
        collectionId={collectionId}
        fieldKey={convertFieldKey}
        fieldLabel={fields.find((f) => f.key === convertFieldKey)?.label ?? convertFieldKey ?? ''}
        pending={convertMutation.isPending}
        onApply={(key) => convertMutation.mutate(key)}
        onClose={() => setConvertFieldKey(null)}
      />
    </div>
  );
}

// ── Convert-to-select modal: preview distinct values, then apply ──
function ConvertToSelectModal({ siteId, collectionId, fieldKey, fieldLabel, pending, onApply, onClose }: {
  siteId: string;
  collectionId: string;
  fieldKey: string | null;
  fieldLabel: string;
  pending: boolean;
  onApply: (fieldKey: string) => void;
  onClose: () => void;
}) {
  const { data: preview, isLoading, error } = useQuery({
    queryKey: ['convert-preview', siteId, collectionId, fieldKey],
    queryFn: () => collections.convertPreview(siteId, collectionId, fieldKey!).then((r) => r.data.data),
    enabled: !!fieldKey,
  });

  return (
    <Modal open={!!fieldKey} onClose={onClose} title={`Convert “${fieldLabel}” to a select`}>
      {isLoading && <div className="flex justify-center py-10"><Loader2 className="h-6 w-6 animate-spin text-base-content/30" /></div>}
      {!!error && <div className="border border-error/30 bg-error/10 rounded-box p-3 text-[13px] text-error">{apiErr(error)}</div>}
      {preview && (
        <div className="space-y-3">
          <p className="text-[13px] text-base-content/60">
            The distinct values below become the select options; every record keeps its current value.
          </p>
          <div className="border border-base-300/30 rounded-box max-h-56 overflow-y-auto divide-y divide-base-300/15">
            {preview.distinct.map(({ value, count }) => (
              <div key={value} className="flex items-center justify-between px-3 py-1.5 text-[13px]">
                <span className="truncate text-base-content/80">{value || <em className="text-base-content/35">empty</em>}</span>
                <span className="text-[11px] text-base-content/40 tabular-nums shrink-0 ml-3">{count} record{count === 1 ? '' : 's'}</span>
              </div>
            ))}
            {preview.distinct.length === 0 && (
              <p className="px-3 py-4 text-[12px] text-base-content/35">No values yet — the select starts with no options.</p>
            )}
          </div>
          {!preview.convertible && (
            <div className="border border-warning/40 bg-warning/10 rounded-box p-3 text-[12px] text-base-content/70">
              This field can’t be converted — it has too many distinct values to make a sensible fixed list. Selects work best with a small, repeating set of values.
            </div>
          )}
          <div className="flex justify-end gap-2 pt-1">
            <button onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
            <button
              onClick={() => fieldKey && onApply(fieldKey)}
              disabled={!preview.convertible || pending}
              className="btn btn-primary btn-sm gap-1.5 text-[12px]"
            >
              {pending && <Loader2 size={13} className="animate-spin" />}
              Convert field
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
}

/** Drop empty optional keys before sending to the API. */
function cleanField(f: CollectionField): CollectionField {
  const out: CollectionField = { key: f.key, label: f.label.trim(), type: f.type };
  const allowedFlags = f.type === 'computed'
    ? (['show_in_list'] as const)
    : (['required', 'unique', 'searchable', 'facetable', 'show_in_list'] as const);
  for (const flag of allowedFlags) {
    if (f[flag]) out[flag] = true;
  }
  if (f.description?.trim()) out.description = f.description.trim();
  if (f.type === 'select' || f.type === 'multi_select') out.options = (f.options ?? []).filter((o) => o.trim());
  if (f.type === 'computed' && f.computed) {
    out.computed = {
      fn: f.computed.fn,
      collection_id: f.computed.collection_id,
      relation_key: f.computed.relation_key,
      ...(f.computed.fn === 'sum' && f.computed.sum_field ? { sum_field: f.computed.sum_field } : {}),
    };
  }
  if (supportsDefault(f.type) && f.default !== undefined && f.default !== null && f.default !== ''
    && !(Array.isArray(f.default) && f.default.length === 0)) {
    out.default = f.default;
  }
  if (f.type === 'relation' && f.relation) {
    out.relation = {
      collection_id: f.relation.collection_id,
      mode: f.relation.mode,
      ...(f.relation.mode === 'many' && (f.relation.pivot_fields?.length ?? 0) > 0
        ? { pivot_fields: f.relation.pivot_fields }
        : {}),
    };
  }
  if (f.settings) {
    const s = Object.fromEntries(Object.entries(f.settings).filter(([, v]) => v !== undefined && v !== '' && v !== null));
    if (Object.keys(s).length > 0) out.settings = s;
  }
  return out;
}

// ── Field row (sortable) ──
function SortableFieldRow({ id, field, selected, error, isTitle, onSelect, onRemove, onMoveUp, onMoveDown }: {
  id: string;
  field: CollectionField;
  selected: boolean;
  error: string | null;
  isTitle: boolean;
  onSelect: () => void;
  onRemove: () => void;
  onMoveUp?: () => void;
  onMoveDown?: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  const meta = FIELD_TYPE_META[field.type];
  const flags: string[] = [];
  if (field.required) flags.push('required');
  if (field.unique) flags.push('unique');
  if (field.searchable) flags.push('searchable');
  if (field.facetable) flags.push('facetable');
  if (field.show_in_list) flags.push('in list');

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={`flex items-center gap-2 px-3 py-2.5 cursor-pointer transition-colors ${
        isDragging ? 'opacity-50' : ''
      } ${selected ? 'bg-primary/10' : 'hover:bg-base-300/10'}`}
      onClick={onSelect}
    >
      <button {...attributes} {...listeners} onClick={(e) => e.stopPropagation()}
        className="text-base-content/20 hover:text-base-content/50 cursor-grab active:cursor-grabbing touch-none" title="Drag to reorder">
        <GripVertical size={14} />
      </button>
      <meta.icon size={14} className="text-base-content/40 shrink-0" strokeWidth={1.5} />
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-[13px] font-medium text-base-content truncate">{field.label || <em className="text-base-content/30">unnamed</em>}</span>
          {field.key && <code className="text-[11px] text-base-content/35 font-mono">{field.key}</code>}
          {isTitle && <span className="badge badge-primary badge-outline badge-xs text-[10px]">title</span>}
        </div>
        <div className="flex items-center gap-1 mt-0.5 flex-wrap">
          <span className="badge badge-ghost badge-xs text-[10px]">{meta.label}</span>
          {flags.map((fl) => <span key={fl} className="badge badge-outline badge-xs text-[10px] text-base-content/40">{fl}</span>)}
          {error && <span className="text-[11px] text-error flex items-center gap-1"><AlertTriangle size={10} /> {error}</span>}
        </div>
      </div>
      <div className="flex items-center gap-0.5" onClick={(e) => e.stopPropagation()}>
        <button onClick={onMoveUp} disabled={!onMoveUp} className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move up"><ChevronUp size={13} /></button>
        <button onClick={onMoveDown} disabled={!onMoveDown} className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move down"><ChevronDown size={13} /></button>
        <button onClick={onRemove} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Remove field"><Trash2 size={13} /></button>
      </div>
    </div>
  );
}

// ── Settings panel for one field ──
function FieldSettingsPanel({ field, idx, locked, onUnlock, allFields, collectionsForRelation, currentCollection, serverError, clientError, onChange, onClose, onRequestConvert }: {
  field: CollectionField;
  idx: number;
  locked: boolean;
  onUnlock: () => void;
  allFields: CollectionField[];
  collectionsForRelation: Collection[];
  currentCollection: Collection;
  serverError: string | null;
  clientError: string | null;
  onChange: (patch: Partial<CollectionField>) => void;
  onClose: () => void;
  onRequestConvert?: () => void;
}) {
  const meta = FIELD_TYPE_META[field.type];
  // Auto-suggest key from label while the key hasn't diverged (new fields only)
  const [keyTouched, setKeyTouched] = useState(() => field.key !== '' && field.key !== keyFromLabel(field.label));
  useEffect(() => {
    setKeyTouched(field.key !== '' && field.key !== keyFromLabel(field.label));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idx]);

  const otherKeys = allFields.filter((f) => f !== field).map((f) => f.key);
  const keyErr = fieldKeyError(field.key, otherKeys);
  const applicable = settingsForType(field.type);

  const setSetting = (k: keyof NonNullable<CollectionField['settings']>, v: unknown) => {
    onChange({ settings: { ...field.settings, [k]: v === '' || v === undefined ? undefined : v } });
  };

  return (
    <div className="border border-base-300/40 rounded-box bg-base-100 sticky top-4">
      <div className="flex items-center justify-between px-4 py-2.5 border-b border-base-300/30">
        <div className="flex items-center gap-2">
          <meta.icon size={14} className="text-base-content/50" strokeWidth={1.5} />
          <h3 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">{meta.label} field</h3>
        </div>
        <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={13} /></button>
      </div>

      <div className="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
        {(serverError || clientError) && (
          <div className="border border-error/30 bg-error/10 rounded-box px-3 py-2 text-[12px] text-error">
            {serverError || clientError}
          </div>
        )}

        {/* Label + key */}
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Label</label>
          <input
            autoFocus={!field.label}
            value={field.label}
            onChange={(e) => {
              const label = e.target.value;
              onChange(keyTouched || locked ? { label } : { label, key: keyFromLabel(label) });
            }}
            placeholder="e.g. Release date"
            className="input input-bordered input-sm w-full text-[13px]"
          />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 flex items-center gap-1.5">
            Key
            {locked && (
              <button onClick={onUnlock} className="flex items-center gap-1 text-[10px] text-warning hover:underline" title="Unlock to rename — existing data stays under the old key">
                <Lock size={9} /> locked — unlock
              </button>
            )}
            {!locked && field.key && <Unlock size={9} className="text-base-content/25" />}
          </label>
          <input
            value={field.key}
            disabled={locked}
            onChange={(e) => { setKeyTouched(true); onChange({ key: e.target.value }); }}
            placeholder="release_date"
            className={`input input-bordered input-sm w-full text-[13px] font-mono ${keyErr && field.key ? 'input-error' : ''} disabled:opacity-60`}
          />
          {keyErr && field.key !== '' && <p className="text-[11px] text-error mt-1">{keyErr}</p>}
          {!locked && <p className="text-[11px] text-base-content/30 mt-1">Data is stored under this key; it locks after the first save.</p>}
        </div>

        {/* Description */}
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Description <span className="text-base-content/30">(shown to editors, optional)</span></label>
          <input
            value={field.description ?? ''}
            maxLength={200}
            onChange={(e) => onChange({ description: e.target.value })}
            className="input input-bordered input-sm w-full text-[13px]"
          />
        </div>

        {/* Flags */}
        <div>
          <label className="text-[11px] text-base-content/50 mb-1.5 block">Behaviour</label>
          <div className="space-y-1.5">
            {FLAG_LABELS.map(({ flag, label, hint }) => {
              const disabledReason = flagDisabledReason(flag, field.type);
              return (
                <label key={flag}
                  className={`flex items-center gap-2.5 text-[13px] ${disabledReason ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}`}
                  title={disabledReason ?? hint}>
                  <input
                    type="checkbox"
                    className="toggle toggle-xs toggle-primary"
                    checked={!!field[flag] && !disabledReason}
                    disabled={!!disabledReason}
                    onChange={(e) => onChange({ [flag]: e.target.checked })}
                  />
                  <span className="text-base-content/80">{label}</span>
                  <span className="text-[11px] text-base-content/30 truncate">{disabledReason ?? hint}</span>
                </label>
              );
            })}
          </div>
        </div>

        {/* Options editor (select / multi_select) */}
        {(field.type === 'select' || field.type === 'multi_select') && (
          <OptionsEditor
            options={field.options ?? []}
            onChange={(options) => onChange({ options })}
          />
        )}

        {/* Relation config */}
        {field.type === 'relation' && (
          <RelationConfigEditor
            field={field}
            collectionsForRelation={collectionsForRelation}
            currentCollection={currentCollection}
            onChange={onChange}
          />
        )}

        {/* Computed rollup config */}
        {field.type === 'computed' && (
          <ComputedConfigEditor
            field={field}
            sourceCollections={[currentCollection, ...collectionsForRelation]}
            currentCollection={currentCollection}
            onChange={onChange}
          />
        )}

        {/* Convert text → select */}
        {onRequestConvert && (
          <div className="border-t border-base-300/20 pt-3">
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Type conversion</label>
            <button onClick={onRequestConvert} className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary border border-base-300/40">
              Convert to select…
            </button>
            <p className="text-[11px] text-base-content/30 mt-1">Turns the values already in records into a fixed option list.</p>
          </div>
        )}

        {/* Validation (regex pattern for text/sku) */}
        {applicable.pattern && (
          <div className="border-t border-base-300/20 pt-3">
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Validation <span className="text-base-content/30">(optional)</span></label>
            <div className="space-y-1.5">
              <input
                value={field.settings?.pattern ?? ''}
                onChange={(e) => setSetting('pattern', e.target.value)}
                placeholder="Regex pattern, e.g. ^[A-Z]{2}-\d{4}$"
                className="input input-bordered input-xs w-full text-[12px] font-mono"
              />
              <input
                value={field.settings?.pattern_message ?? ''}
                onChange={(e) => setSetting('pattern_message', e.target.value)}
                placeholder="Message shown when the value doesn’t match"
                className="input input-bordered input-xs w-full text-[12px]"
              />
            </div>
          </div>
        )}

        {/* Date bounds */}
        {applicable.dateRange && (
          <div className="border-t border-base-300/20 pt-3">
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Allowed range <span className="text-base-content/30">(optional)</span></label>
            <div className="grid grid-cols-2 gap-2">
              <input
                type="date"
                value={(field.settings?.min as string) ?? ''}
                onChange={(e) => setSetting('min', e.target.value)}
                title="Earliest allowed date"
                className="input input-bordered input-xs w-full text-[12px]"
              />
              <input
                type="date"
                value={(field.settings?.max as string) ?? ''}
                onChange={(e) => setSetting('max', e.target.value)}
                title="Latest allowed date"
                className="input input-bordered input-xs w-full text-[12px]"
              />
            </div>
          </div>
        )}

        {/* Default value */}
        {supportsDefault(field.type) && (
          <div className="border-t border-base-300/20 pt-3">
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Default value <span className="text-base-content/30">(pre-filled on new records)</span></label>
            <DefaultValueInput field={field} onChange={(v) => onChange({ default: v })} />
          </div>
        )}

        {/* Optional settings */}
        {(applicable.placeholder || applicable.maxLength || applicable.range || applicable.rows) && (
          <div className="border-t border-base-300/20 pt-3">
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Input settings <span className="text-base-content/30">(optional)</span></label>
            <div className="grid grid-cols-12 gap-2">
              {applicable.placeholder && (
                <div className="col-span-12">
                  <input value={field.settings?.placeholder ?? ''} onChange={(e) => setSetting('placeholder', e.target.value)}
                    placeholder="Placeholder text" className="input input-bordered input-xs w-full text-[12px]" />
                </div>
              )}
              <div className="col-span-12">
                <input value={field.settings?.help ?? ''} onChange={(e) => setSetting('help', e.target.value)}
                  placeholder="Help text under the input" className="input input-bordered input-xs w-full text-[12px]" />
              </div>
              {applicable.maxLength && (
                <div className="col-span-4">
                  <input type="number" min={1} value={field.settings?.max_length ?? ''}
                    onChange={(e) => setSetting('max_length', e.target.value === '' ? undefined : Number(e.target.value))}
                    placeholder="Max len" className="input input-bordered input-xs w-full text-[12px]" />
                </div>
              )}
              {applicable.range && (
                <>
                  <div className="col-span-4">
                    <input type="number" value={field.settings?.min ?? ''}
                      onChange={(e) => setSetting('min', e.target.value === '' ? undefined : Number(e.target.value))}
                      placeholder="Min" className="input input-bordered input-xs w-full text-[12px]" />
                  </div>
                  <div className="col-span-4">
                    <input type="number" value={field.settings?.max ?? ''}
                      onChange={(e) => setSetting('max', e.target.value === '' ? undefined : Number(e.target.value))}
                      placeholder="Max" className="input input-bordered input-xs w-full text-[12px]" />
                  </div>
                  <div className="col-span-4">
                    <input type="number" min={0} step="any" value={field.settings?.step ?? ''}
                      onChange={(e) => setSetting('step', e.target.value === '' ? undefined : Number(e.target.value))}
                      placeholder="Step" className="input input-bordered input-xs w-full text-[12px]" />
                  </div>
                </>
              )}
              {applicable.rows && (
                <div className="col-span-4">
                  <input type="number" min={2} value={field.settings?.rows ?? ''}
                    onChange={(e) => setSetting('rows', e.target.value === '' ? undefined : Number(e.target.value))}
                    placeholder="Rows" className="input input-bordered input-xs w-full text-[12px]" />
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Options editor for select / multi_select ──
function OptionsEditor({ options, onChange }: { options: string[]; onChange: (opts: string[]) => void }) {
  const [draft, setDraft] = useState('');

  const add = () => {
    const v = draft.trim();
    if (!v || options.includes(v)) return;
    onChange([...options, v]);
    setDraft('');
  };

  return (
    <div className="border-t border-base-300/20 pt-3">
      <label className="text-[11px] text-base-content/50 mb-1.5 block">Options</label>
      <div className="space-y-1">
        {options.map((opt, i) => (
          <div key={`${opt}-${i}`} className="flex items-center gap-1.5">
            <input value={opt}
              onChange={(e) => onChange(options.map((o, j) => (j === i ? e.target.value : o)))}
              className="input input-bordered input-xs flex-1 text-[12px]" />
            <button onClick={() => i > 0 && onChange(arrayMove(options, i, i - 1))} disabled={i === 0}
              className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move up"><ChevronUp size={12} /></button>
            <button onClick={() => i < options.length - 1 && onChange(arrayMove(options, i, i + 1))} disabled={i === options.length - 1}
              className="btn btn-ghost btn-xs btn-square disabled:opacity-20" title="Move down"><ChevronDown size={12} /></button>
            <button onClick={() => onChange(options.filter((_, j) => j !== i))}
              className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Remove"><X size={12} /></button>
          </div>
        ))}
      </div>
      <div className="flex items-center gap-1.5 mt-1.5">
        <input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }}
          placeholder="Add an option and press Enter"
          className="input input-bordered input-xs flex-1 text-[12px]"
        />
        <button onClick={add} disabled={!draft.trim()} className="btn btn-ghost btn-xs gap-1 text-[11px] text-primary"><Plus size={12} /> Add</button>
      </div>
      {options.length === 0 && <p className="text-[11px] text-warning mt-1">Add at least one option.</p>}
    </div>
  );
}

// ── Computed rollup configuration (fn, source collection, relation key, sum field) ──
function ComputedConfigEditor({ field, sourceCollections, currentCollection, onChange }: {
  field: CollectionField;
  sourceCollections: Collection[];
  currentCollection: Collection;
  onChange: (patch: Partial<CollectionField>) => void;
}) {
  const cfg = field.computed ?? { fn: 'count' as const, collection_id: '', relation_key: '' };
  const setCfg = (patch: Partial<NonNullable<CollectionField['computed']>>) => {
    onChange({ computed: { ...cfg, ...patch } });
  };

  const source = sourceCollections.find((c) => c.id === cfg.collection_id) ?? null;
  // Relation fields on the source that point back at THIS collection
  const relationKeys = (source?.schema?.fields ?? []).filter(
    (f) => f.type === 'relation' && f.relation?.collection_id === currentCollection.id,
  );
  const sumFields = (source?.schema?.fields ?? []).filter((f) => f.type === 'number' || f.type === 'price');

  return (
    <div className="border-t border-base-300/20 pt-3 space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1.5 block">Rollup</label>
        <div className="flex gap-4">
          {(['count', 'sum'] as const).map((fn) => (
            <label key={fn} className="flex items-center gap-1.5 text-[13px] text-base-content/80 cursor-pointer">
              <input type="radio" className="radio radio-xs radio-primary" checked={cfg.fn === fn}
                onChange={() => setCfg({ fn, ...(fn === 'count' ? { sum_field: undefined } : {}) })} />
              {fn === 'count' ? 'Count related records' : 'Sum a field on them'}
            </label>
          ))}
        </div>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Source collection</label>
        <select
          value={cfg.collection_id}
          onChange={(e) => setCfg({ collection_id: e.target.value, relation_key: '', sum_field: undefined })}
          className="select select-bordered select-sm w-full text-[13px]"
        >
          <option value="">— pick a collection —</option>
          {sourceCollections.map((c) => (
            <option key={c.id} value={c.id}>{c.name}{c.id === currentCollection.id ? ' (this collection)' : ''}</option>
          ))}
        </select>
      </div>
      {source && (
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Via relation</label>
          {relationKeys.length === 0 ? (
            <p className="text-[12px] text-base-content/40">
              “{source.name}” has no relation field pointing at {currentCollection.name} — add one there first.
            </p>
          ) : (
            <select
              value={cfg.relation_key}
              onChange={(e) => setCfg({ relation_key: e.target.value })}
              className="select select-bordered select-sm w-full text-[13px]"
            >
              <option value="">— pick a relation field —</option>
              {relationKeys.map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
            </select>
          )}
        </div>
      )}
      {source && cfg.fn === 'sum' && (
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Field to sum</label>
          {sumFields.length === 0 ? (
            <p className="text-[12px] text-base-content/40">“{source.name}” has no number or price fields to sum.</p>
          ) : (
            <select
              value={cfg.sum_field ?? ''}
              onChange={(e) => setCfg({ sum_field: e.target.value || undefined })}
              className="select select-bordered select-sm w-full text-[13px]"
            >
              <option value="">— pick a numeric field —</option>
              {sumFields.map((f) => <option key={f.key} value={f.key}>{f.label || f.key}</option>)}
            </select>
          )}
        </div>
      )}
      <p className="text-[11px] text-base-content/30">Display-only — the value is resolved when the site is published.</p>
    </div>
  );
}

// ── Default value input, typed per field type ──
function DefaultValueInput({ field, onChange }: {
  field: CollectionField;
  onChange: (v: unknown) => void;
}) {
  const v = field.default;
  switch (field.type) {
    case 'boolean':
      return (
        <label className="flex items-center gap-2.5 cursor-pointer w-fit">
          <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!v}
            onChange={(e) => onChange(e.target.checked ? true : undefined)} />
          <span className="text-[12px] text-base-content/60">{v ? 'Yes' : 'No'}</span>
        </label>
      );
    case 'select':
      return (
        <select value={(v as string) ?? ''} onChange={(e) => onChange(e.target.value || undefined)}
          className="select select-bordered select-xs w-full text-[12px]">
          <option value="">— no default —</option>
          {(field.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
      );
    case 'multi_select': {
      const selected = Array.isArray(v) ? (v as string[]) : [];
      return (
        <div className="flex flex-wrap gap-1">
          {(field.options ?? []).map((o) => {
            const on = selected.includes(o);
            return (
              <button key={o} type="button"
                onClick={() => {
                  const next = on ? selected.filter((x) => x !== o) : [...selected, o];
                  onChange(next.length > 0 ? next : undefined);
                }}
                className={`badge badge-sm text-[11px] cursor-pointer ${on ? 'badge-primary' : 'badge-outline text-base-content/50'}`}>
                {o}
              </button>
            );
          })}
          {(field.options ?? []).length === 0 && <span className="text-[11px] text-base-content/30">Add options first.</span>}
        </div>
      );
    }
    case 'number':
    case 'price':
      return (
        <input type="number" step={field.type === 'price' ? 0.01 : undefined}
          value={v === undefined || v === null || v === '' ? '' : String(v)}
          onChange={(e) => onChange(e.target.value === '' ? undefined : Number(e.target.value))}
          className="input input-bordered input-xs w-full text-[12px] tabular-nums" />
      );
    case 'date':
      return (
        <input type="date" value={(v as string) ?? ''}
          onChange={(e) => onChange(e.target.value || undefined)}
          className="input input-bordered input-xs w-full text-[12px]" />
      );
    case 'rich_text':
      return (
        <textarea value={(v as string) ?? ''} rows={3}
          onChange={(e) => onChange(e.target.value || undefined)}
          placeholder="Default HTML / text"
          className="textarea textarea-bordered w-full text-[12px] leading-relaxed" />
      );
    default: // text, email, url, phone, sku
      return (
        <input value={(v as string) ?? ''}
          onChange={(e) => onChange(e.target.value || undefined)}
          className={`input input-bordered input-xs w-full text-[12px] ${field.type === 'sku' ? 'font-mono uppercase' : ''}`} />
      );
  }
}

// ── Relation configuration (target, mode, pivot fields) ──
function RelationConfigEditor({ field, collectionsForRelation, currentCollection, onChange }: {
  field: CollectionField;
  collectionsForRelation: Collection[];
  currentCollection: Collection;
  onChange: (patch: Partial<CollectionField>) => void;
}) {
  const rel = field.relation ?? { collection_id: '', mode: 'one' as const };
  const setRel = (patch: Partial<NonNullable<CollectionField['relation']>>) => {
    onChange({ relation: { ...rel, ...patch } });
  };

  const pivotFields = rel.pivot_fields ?? [];
  const setPivot = (pf: CollectionPivotField[]) => setRel({ pivot_fields: pf });

  return (
    <div className="border-t border-base-300/20 pt-3 space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Target collection</label>
        <select
          value={rel.collection_id}
          onChange={(e) => setRel({ collection_id: e.target.value })}
          className="select select-bordered select-sm w-full text-[13px]"
        >
          <option value="">— pick a collection —</option>
          {/* Self-relations are allowed (e.g. "related products") */}
          {[currentCollection, ...collectionsForRelation].map((c) => (
            <option key={c.id} value={c.id}>{c.name}{c.id === currentCollection.id ? ' (this collection)' : ''}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1.5 block">Mode</label>
        <div className="flex gap-4">
          {(['one', 'many'] as const).map((m) => (
            <label key={m} className="flex items-center gap-1.5 text-[13px] text-base-content/80 cursor-pointer">
              <input type="radio" className="radio radio-xs radio-primary" checked={rel.mode === m}
                onChange={() => setRel({ mode: m, ...(m === 'one' ? { pivot_fields: undefined } : {}) })} />
              {m === 'one' ? 'One record' : 'Many records'}
            </label>
          ))}
        </div>
      </div>

      {rel.mode === 'many' && (
        <div>
          <label className="text-[11px] text-base-content/50 mb-1.5 block">
            Pivot fields <span className="text-base-content/30">(extra data per linked record, e.g. quantity)</span>
          </label>
          <div className="space-y-2">
            {pivotFields.map((pf, i) => (
              <div key={i} className="border border-base-300/30 rounded-box p-2 space-y-1.5">
                <div className="grid grid-cols-12 gap-1.5">
                  <input value={pf.label} placeholder="Label"
                    onChange={(e) => {
                      const label = e.target.value;
                      setPivot(pivotFields.map((x, j) => (j === i ? { ...x, label, key: x.key === keyFromLabel(x.label) || !x.key ? keyFromLabel(label) : x.key } : x)));
                    }}
                    className="input input-bordered input-xs col-span-4 text-[12px]" />
                  <input value={pf.key} placeholder="key"
                    onChange={(e) => setPivot(pivotFields.map((x, j) => (j === i ? { ...x, key: e.target.value } : x)))}
                    className="input input-bordered input-xs col-span-3 text-[12px] font-mono" />
                  <select value={pf.type}
                    onChange={(e) => setPivot(pivotFields.map((x, j) => (j === i ? { ...x, type: e.target.value as CollectionPivotFieldType, ...(e.target.value !== 'select' ? { options: undefined } : {}) } : x)))}
                    className="select select-bordered select-xs col-span-3 text-[12px]">
                    {PIVOT_FIELD_TYPES.map((t) => <option key={t} value={t}>{FIELD_TYPE_META[t].label}</option>)}
                  </select>
                  <label className="col-span-1 flex items-center justify-center" title="Required">
                    <input type="checkbox" className="checkbox checkbox-xs" checked={!!pf.required}
                      onChange={(e) => setPivot(pivotFields.map((x, j) => (j === i ? { ...x, required: e.target.checked } : x)))} />
                  </label>
                  <button onClick={() => setPivot(pivotFields.filter((_, j) => j !== i))}
                    className="btn btn-ghost btn-xs btn-square col-span-1 text-base-content/40 hover:text-error" title="Remove"><X size={12} /></button>
                </div>
                {pf.type === 'select' && (
                  <input
                    value={(pf.options ?? []).join(', ')}
                    onChange={(e) => setPivot(pivotFields.map((x, j) => (j === i ? { ...x, options: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) } : x)))}
                    placeholder="Options, comma-separated"
                    className="input input-bordered input-xs w-full text-[12px]"
                  />
                )}
              </div>
            ))}
          </div>
          <button
            onClick={() => setPivot([...pivotFields, { key: '', label: '', type: 'text' }])}
            className="btn btn-ghost btn-xs gap-1 text-[11px] text-primary mt-1.5"
          >
            <Plus size={12} /> Add pivot field
          </button>
        </div>
      )}
    </div>
  );
}
