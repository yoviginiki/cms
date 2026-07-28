import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import {
  ArrowLeft, Loader2, Plus, Trash2, ChevronRight, ChevronDown, FolderTree,
  Pencil, Check, X, CornerUpLeft,
} from 'lucide-react';
import {
  collections, collectionCategories, sites,
  type Collection, type CategoryNode, type CollectionField, type CollectionFieldType,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { FIELD_TYPE_META, FIELD_TYPE_GROUPS, keyFromLabel, fieldKeyError } from '@/lib/collectionFieldTypes';
import { apiErr, validationErrors } from './shared';

/** Flatten a tree into rows carrying depth, for a simple indented list. */
function flatten(nodes: CategoryNode[], expanded: Set<string>, depth = 0): { node: CategoryNode; depth: number; hasChildren: boolean }[] {
  const out: { node: CategoryNode; depth: number; hasChildren: boolean }[] = [];
  for (const node of nodes) {
    const hasChildren = node.children.length > 0;
    out.push({ node, depth, hasChildren });
    if (hasChildren && expanded.has(node.id)) {
      out.push(...flatten(node.children, expanded, depth + 1));
    }
  }
  return out;
}

/** Collect every node into a flat map (id → node) for lookups. */
function indexTree(nodes: CategoryNode[], acc: Record<string, CategoryNode> = {}): Record<string, CategoryNode> {
  for (const n of nodes) {
    acc[n.id] = n;
    indexTree(n.children, acc);
  }
  return acc;
}

export default function CollectionCategories() {
  const { siteId = '', collectionId = '' } = useParams();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const [expanded, setExpanded] = useState<Set<string>>(new Set());
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [renamingId, setRenamingId] = useState<string | null>(null);
  const [renameValue, setRenameValue] = useState('');
  const [addingUnder, setAddingUnder] = useState<string | null | false>(false); // false = closed, null = root, id = child
  const [newName, setNewName] = useState('');
  const [moveTarget, setMoveTarget] = useState<CategoryNode | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<CategoryNode | null>(null);

  const { data: collection } = useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collections.get(siteId, collectionId).then((r) => r.data.data),
  });

  const { data: tree = [], isLoading, error } = useQuery<CategoryNode[]>({
    queryKey: ['category-tree', siteId, collectionId],
    queryFn: () => collectionCategories.tree(siteId, collectionId).then((r) => r.data.data),
  });

  const nodeIndex = useMemo(() => indexTree(tree), [tree]);
  const selected = selectedId ? nodeIndex[selectedId] : null;
  const rows = useMemo(() => flatten(tree, expanded), [tree, expanded]);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['category-tree', siteId, collectionId] });

  const createMutation = useMutation({
    mutationFn: (body: { name: string; parent_id: string | null }) => collectionCategories.create(siteId, collectionId, body),
    onSuccess: (res, vars) => {
      invalidate();
      setAddingUnder(false);
      setNewName('');
      if (vars.parent_id) setExpanded((e) => new Set(e).add(vars.parent_id!));
      setSelectedId(res.data.data.id);
      toast({ type: 'success', message: 'Category added.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const renameMutation = useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) => collectionCategories.update(siteId, collectionId, id, { name }),
    onSuccess: () => { invalidate(); setRenamingId(null); toast({ type: 'success', message: 'Renamed.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const moveMutation = useMutation({
    mutationFn: ({ id, parent_id }: { id: string; parent_id: string | null }) => collectionCategories.move(siteId, collectionId, id, { parent_id }),
    onSuccess: () => { invalidate(); setMoveTarget(null); toast({ type: 'success', message: 'Moved.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const deleteMutation = useMutation({
    mutationFn: ({ id, mode }: { id: string; mode: 'reparent' | 'cascade' }) => collectionCategories.delete(siteId, collectionId, id, mode),
    onSuccess: () => {
      invalidate();
      if (selectedId === deleteTarget?.id) setSelectedId(null);
      setDeleteTarget(null);
      toast({ type: 'success', message: 'Category deleted.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const toggle = (id: string) => setExpanded((e) => { const n = new Set(e); n.has(id) ? n.delete(id) : n.add(id); return n; });

  // Candidate parents for a move: every node except the one being moved and its
  // descendants (server also guards, this just trims the picker).
  const moveCandidates = useMemo(() => {
    if (!moveTarget) return [];
    const banned = new Set<string>();
    const walk = (n: CategoryNode) => { banned.add(n.id); n.children.forEach(walk); };
    walk(moveTarget);
    return Object.values(nodeIndex).filter((n) => !banned.has(n.id));
  }, [moveTarget, nodeIndex]);

  return (
    <div className="max-w-6xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/sites/${siteId}/collections/${collectionId}/records`} className="btn btn-ghost btn-sm btn-square text-base-content/40">
          <ArrowLeft size={16} />
        </Link>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-bold text-base-content flex items-center gap-2">
            <FolderTree size={18} className="text-base-content/40" /> Categories
          </h1>
          <p className="text-[13px] text-base-content/50">
            {collection?.icon ? `${collection.icon} ` : ''}{collection?.name} — a category tree where each category can define its own extra fields.
          </p>
        </div>
        <button onClick={() => { setAddingUnder(null); setNewName(''); }} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          <Plus size={14} /> Root category
        </button>
      </div>

      {isLoading && <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {!!error && <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the category tree.</div>}

      {!isLoading && !error && (
        <div className="grid grid-cols-12 gap-5">
          {/* Tree */}
          <div className="col-span-12 lg:col-span-6">
            <div className="rounded-box border border-base-300/40 bg-base-100">
              {addingUnder === null && (
                <AddRow
                  placeholder="New root category name"
                  value={newName}
                  onChange={setNewName}
                  pending={createMutation.isPending}
                  onCancel={() => setAddingUnder(false)}
                  onSubmit={() => newName.trim() && createMutation.mutate({ name: newName.trim(), parent_id: null })}
                />
              )}

              {tree.length === 0 && addingUnder === false && (
                <div className="p-6">
                  <EmptyState
                    icon={FolderTree}
                    title="No categories yet"
                    description="Build a category tree to organise records — and give each branch its own fields."
                    actionLabel="Add a root category"
                    onAction={() => { setAddingUnder(null); setNewName(''); }}
                  />
                </div>
              )}

              <div className="divide-y divide-base-300/15">
                {rows.map(({ node, depth, hasChildren }) => (
                  <div key={node.id}>
                    <div
                      className={`flex items-center gap-1 pr-2 py-1.5 hover:bg-base-300/10 transition-colors cursor-pointer ${selectedId === node.id ? 'bg-primary/5' : ''}`}
                      style={{ paddingLeft: `${8 + depth * 18}px` }}
                      onClick={() => setSelectedId(node.id)}
                    >
                      <button
                        onClick={(e) => { e.stopPropagation(); if (hasChildren) toggle(node.id); }}
                        className={`btn btn-ghost btn-xs btn-square ${hasChildren ? '' : 'invisible'}`}
                      >
                        {expanded.has(node.id) ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
                      </button>

                      {renamingId === node.id ? (
                        <div className="flex items-center gap-1 flex-1" onClick={(e) => e.stopPropagation()}>
                          <input
                            autoFocus
                            value={renameValue}
                            onChange={(e) => setRenameValue(e.target.value)}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter' && renameValue.trim()) renameMutation.mutate({ id: node.id, name: renameValue.trim() });
                              if (e.key === 'Escape') setRenamingId(null);
                            }}
                            className="input input-bordered input-xs flex-1 text-[13px]"
                          />
                          <button onClick={() => renameValue.trim() && renameMutation.mutate({ id: node.id, name: renameValue.trim() })} className="btn btn-ghost btn-xs btn-square text-success"><Check size={13} /></button>
                          <button onClick={() => setRenamingId(null)} className="btn btn-ghost btn-xs btn-square"><X size={13} /></button>
                        </div>
                      ) : (
                        <>
                          <span className="text-[13px] text-base-content flex-1 truncate">
                            {node.name}
                            {(node.schema?.fields?.length ?? 0) > 0 && (
                              <span className="ml-1.5 badge badge-ghost badge-xs text-[10px]">+{node.schema.fields.length} field{node.schema.fields.length === 1 ? '' : 's'}</span>
                            )}
                          </span>
                          {typeof node.record_count === 'number' && node.record_count > 0 && (
                            <span className="text-[11px] text-base-content/35 tabular-nums mr-1">{node.record_count}</span>
                          )}
                          <div className="flex items-center opacity-0 group-hover:opacity-100 hover:opacity-100">
                            <button onClick={(e) => { e.stopPropagation(); setAddingUnder(node.id); setNewName(''); setExpanded((x) => new Set(x).add(node.id)); }} className="btn btn-ghost btn-xs btn-square text-base-content/40" title="Add subcategory"><Plus size={12} /></button>
                            <button onClick={(e) => { e.stopPropagation(); setRenamingId(node.id); setRenameValue(node.name); }} className="btn btn-ghost btn-xs btn-square text-base-content/40" title="Rename"><Pencil size={12} /></button>
                            <button onClick={(e) => { e.stopPropagation(); setMoveTarget(node); }} className="btn btn-ghost btn-xs btn-square text-base-content/40" title="Move"><CornerUpLeft size={12} /></button>
                            <button onClick={(e) => { e.stopPropagation(); setDeleteTarget(node); }} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Delete"><Trash2 size={12} /></button>
                          </div>
                        </>
                      )}
                    </div>

                    {addingUnder === node.id && (
                      <div style={{ paddingLeft: `${8 + (depth + 1) * 18}px` }}>
                        <AddRow
                          placeholder={`Subcategory of "${node.name}"`}
                          value={newName}
                          onChange={setNewName}
                          pending={createMutation.isPending}
                          onCancel={() => setAddingUnder(false)}
                          onSubmit={() => newName.trim() && createMutation.mutate({ name: newName.trim(), parent_id: node.id })}
                        />
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Node schema panel */}
          <div className="col-span-12 lg:col-span-6">
            {selected ? (
              <NodeSchemaPanel
                key={selected.id}
                siteId={siteId}
                collectionId={collectionId}
                node={selected}
                baseFields={collection?.schema?.fields ?? []}
                chain={buildChain(nodeIndex, selected.id)}
                onSaved={invalidate}
              />
            ) : (
              <div className="rounded-box border border-dashed border-base-300/50 p-8 text-center text-[13px] text-base-content/40">
                Select a category to edit the extra fields records in it will have.
              </div>
            )}
          </div>
        </div>
      )}

      {/* Move dialog */}
      {moveTarget && (
        <MoveDialog
          node={moveTarget}
          candidates={moveCandidates}
          pending={moveMutation.isPending}
          onCancel={() => setMoveTarget(null)}
          onMove={(parentId) => moveMutation.mutate({ id: moveTarget.id, parent_id: parentId })}
        />
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete category"
        message={
          deleteTarget && deleteTarget.children.length > 0
            ? `"${deleteTarget.name}" has subcategories. Deleting it moves them (and any records) up to its parent. Continue?`
            : `Delete "${deleteTarget?.name}"? Records filed here move up to its parent (or become unfiled). The records themselves are kept.`
        }
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id, mode: 'reparent' })}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}

/** Build root→leaf name chain for a node id. */
function buildChain(index: Record<string, CategoryNode>, id: string): { id: string; name: string }[] {
  const chain: { id: string; name: string }[] = [];
  let cur: CategoryNode | undefined = index[id];
  const seen = new Set<string>();
  while (cur && !seen.has(cur.id)) {
    seen.add(cur.id);
    chain.unshift({ id: cur.id, name: cur.name });
    cur = cur.parent_id ? index[cur.parent_id] : undefined;
  }
  return chain;
}

function AddRow({ placeholder, value, onChange, onSubmit, onCancel, pending }: {
  placeholder: string; value: string; onChange: (v: string) => void; onSubmit: () => void; onCancel: () => void; pending: boolean;
}) {
  return (
    <div className="flex items-center gap-1.5 px-3 py-2 bg-base-200/40">
      <input
        autoFocus
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={(e) => { if (e.key === 'Enter') onSubmit(); if (e.key === 'Escape') onCancel(); }}
        placeholder={placeholder}
        className="input input-bordered input-xs flex-1 text-[13px]"
      />
      <button onClick={onSubmit} disabled={pending || !value.trim()} className="btn btn-primary btn-xs gap-1 text-[11px]">
        {pending ? <Loader2 size={11} className="animate-spin" /> : <Plus size={11} />} Add
      </button>
      <button onClick={onCancel} className="btn btn-ghost btn-xs btn-square"><X size={13} /></button>
    </div>
  );
}

function MoveDialog({ node, candidates, onMove, onCancel, pending }: {
  node: CategoryNode; candidates: CategoryNode[]; onMove: (parentId: string | null) => void; onCancel: () => void; pending: boolean;
}) {
  const [parentId, setParentId] = useState<string | null>(node.parent_id);
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onCancel}>
      <div className="bg-base-100 rounded-box border border-base-300/50 shadow-xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
        <h3 className="text-[14px] font-semibold text-base-content mb-3 flex items-center gap-2"><CornerUpLeft size={15} /> Move “{node.name}”</h3>
        <label className="text-[11px] text-base-content/50 mb-1 block">New parent</label>
        <select value={parentId ?? ''} onChange={(e) => setParentId(e.target.value || null)} className="select select-bordered select-sm w-full text-[13px]">
          <option value="">— Root (top level) —</option>
          {candidates.map((c) => (
            <option key={c.id} value={c.id}>{' '.repeat(c.depth * 2)}{c.name}</option>
          ))}
        </select>
        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onCancel} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
          <button onClick={() => onMove(parentId)} disabled={pending} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
            {pending && <Loader2 size={13} className="animate-spin" />} Move here
          </button>
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Per-node schema editor — a compact field builder. Fields defined here are
// added on top of the collection's base fields (and any ancestor node fields)
// for records filed under this category.
// ─────────────────────────────────────────────────────────────────────────────

function NodeSchemaPanel({ siteId, collectionId, node, baseFields, chain, onSaved }: {
  siteId: string; collectionId: string; node: CategoryNode; baseFields: CollectionField[]; chain: { id: string; name: string }[]; onSaved: () => void;
}) {
  const { toast } = useToast();
  const [fields, setFields] = useState<CollectionField[]>(node.schema?.fields ?? []);
  const [translations, setTranslations] = useState<Record<string, string>>(node.name_translations ?? {});
  useEffect(() => { setTranslations(node.name_translations ?? {}); }, [node.id]);
  const { data: site } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
  });
  const defaultLang = site?.settings?.default_language || 'en';
  const extraLangs: string[] = ((site?.settings?.languages as string[]) || []).filter((l) => l && l !== defaultLang);
  const [dirty, setDirty] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [pickerOpen, setPickerOpen] = useState(false);

  const baseKeys = baseFields.map((f) => f.key);

  const touch = () => setDirty(true);
  const update = (i: number, patch: Partial<CollectionField>) => { setFields((f) => f.map((x, j) => (j === i ? { ...x, ...patch } : x))); touch(); };
  const remove = (i: number) => { setFields((f) => f.filter((_, j) => j !== i)); touch(); };
  const add = (type: CollectionFieldType) => {
    setFields((f) => [...f, {
      key: '', label: '', type, required: false,
      ...(type === 'select' || type === 'multi_select' ? { options: [] } : {}),
    } as CollectionField]);
    setPickerOpen(false);
    touch();
  };

  const clientErrors = useMemo(() => {
    const errs: Record<number, string> = {};
    fields.forEach((f, i) => {
      const others = fields.filter((_, j) => j !== i).map((x) => x.key);
      const keyErr = fieldKeyError(f.key, others);
      if (keyErr) errs[i] = keyErr;
      else if (!f.label.trim()) errs[i] = 'Label is required';
      else if ((f.type === 'select' || f.type === 'multi_select') && (f.options ?? []).length === 0) errs[i] = 'Add at least one option';
    });
    return errs;
  }, [fields]);

  const saveMutation = useMutation({
    mutationFn: () => collectionCategories.update(siteId, collectionId, node.id, { schema: { fields }, name_translations: translations }),
    onSuccess: () => { setDirty(false); setServerErrors({}); onSaved(); toast({ type: 'success', message: 'Category fields saved.' }); },
    onError: (e: any) => {
      const errs = validationErrors(e);
      if (Object.keys(errs).length > 0) { setServerErrors(errs); toast({ type: 'error', message: 'Some fields need attention.' }); }
      else toast({ type: 'error', message: apiErr(e) });
    },
  });

  const canSave = dirty && Object.keys(clientErrors).length === 0;

  return (
    <div className="rounded-box border border-base-300/40 bg-base-100 p-4">
      <div className="flex items-center gap-1.5 text-[12px] text-base-content/45 mb-1 flex-wrap">
        {chain.map((c, i) => (
          <span key={c.id} className="flex items-center gap-1.5">
            {i > 0 && <ChevronRight size={11} className="text-base-content/25" />}
            <span className={i === chain.length - 1 ? 'text-base-content/70 font-medium' : ''}>{c.name}</span>
          </span>
        ))}
      </div>
      <p className="text-[12px] text-base-content/45 mb-4">
        Extra fields for records filed under <span className="font-medium text-base-content/70">{node.name}</span>.
        They stack on top of the collection’s base fields{chain.length > 1 ? ' and the parent categories’ fields' : ''}.
        A field key that matches a base field overrides it here.
      </p>

      {extraLangs.length > 0 && (
        <div className="mb-4 rounded-box border border-base-300/40 p-3">
          <p className="text-[11px] text-base-content/40 mb-2">
            Name translations — shown on /{'{'}locale{'}'}/ pages; the slug stays the same, only the language prefix changes.
          </p>
          <div className="grid gap-2" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' }}>
            {extraLangs.map((lang) => (
              <label key={lang} className="flex items-center gap-2 text-[12px]">
                <span className="uppercase text-base-content/40 w-7 shrink-0">{lang}</span>
                <input value={translations[lang] ?? ''} placeholder={node.name}
                  onChange={(e) => { setTranslations((t) => ({ ...t, [lang]: e.target.value })); touch(); }}
                  className="input input-bordered input-sm w-full text-[12px]" />
              </label>
            ))}
          </div>
        </div>
      )}

      {baseFields.length > 0 && (
        <details className="mb-4">
          <summary className="text-[11px] text-base-content/40 cursor-pointer select-none">Base collection fields ({baseFields.length}) — always present</summary>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {baseFields.map((f) => (
              <span key={f.key} className="badge badge-ghost badge-sm text-[11px] gap-1"><span className="text-base-content/40">{FIELD_TYPE_META[f.type]?.label}</span> {f.label}</span>
            ))}
          </div>
        </details>
      )}

      <div className="space-y-2.5">
        {fields.length === 0 && (
          <p className="text-[13px] text-base-content/35 italic py-3 text-center border border-dashed border-base-300/40 rounded-box">
            No category-specific fields yet.
          </p>
        )}
        {fields.map((f, i) => {
          const Icon = FIELD_TYPE_META[f.type]?.icon;
          const overrides = baseKeys.includes(f.key);
          return (
            <div key={i} className={`border rounded-box p-3 ${clientErrors[i] || serverErrors[`fields.${i}.key`] ? 'border-error/40' : 'border-base-300/40'} bg-base-200/20`}>
              <div className="flex items-center gap-2 mb-2">
                {Icon && <Icon size={14} className="text-base-content/40 shrink-0" />}
                <span className="text-[11px] text-base-content/45">{FIELD_TYPE_META[f.type]?.label}</span>
                {overrides && <span className="badge badge-warning badge-xs text-[10px]">overrides base</span>}
                <div className="flex-1" />
                <button onClick={() => remove(i)} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error"><Trash2 size={12} /></button>
              </div>
              <div className="grid grid-cols-12 gap-2">
                <div className="col-span-7">
                  <input
                    value={f.label}
                    onChange={(e) => {
                      const label = e.target.value;
                      const autoKey = !f.key || f.key === keyFromLabel(f.label);
                      update(i, { label, ...(autoKey ? { key: keyFromLabel(label) } : {}) });
                    }}
                    placeholder="Label (e.g. Voltage)"
                    className="input input-bordered input-xs w-full text-[13px]"
                  />
                </div>
                <div className="col-span-5">
                  <input
                    value={f.key}
                    onChange={(e) => update(i, { key: e.target.value })}
                    placeholder="key"
                    className="input input-bordered input-xs w-full text-[12px] font-mono"
                  />
                </div>
              </div>
              {(f.type === 'select' || f.type === 'multi_select') && (
                <div className="mt-2">
                  <input
                    value={(f.options ?? []).join(', ')}
                    onChange={(e) => update(i, { options: e.target.value.split(',').map((o) => o.trim()).filter(Boolean) })}
                    placeholder="Options, comma-separated"
                    className="input input-bordered input-xs w-full text-[12px]"
                  />
                </div>
              )}
              <label className="flex items-center gap-2 mt-2 cursor-pointer w-fit">
                <input type="checkbox" checked={!!f.required} onChange={(e) => update(i, { required: e.target.checked })} className="checkbox checkbox-xs" />
                <span className="text-[12px] text-base-content/60">Required</span>
              </label>
              {(clientErrors[i] || serverErrors[`fields.${i}.key`] || serverErrors[`fields.${i}.label`]) && (
                <p className="text-[11px] text-error mt-1.5">{clientErrors[i] || serverErrors[`fields.${i}.key`] || serverErrors[`fields.${i}.label`]}</p>
              )}
            </div>
          );
        })}
      </div>

      <div className="relative mt-3">
        <button onClick={() => setPickerOpen((o) => !o)} className="btn btn-ghost btn-sm gap-1.5 text-[12px] border border-dashed border-base-300/50 w-full">
          <Plus size={13} /> Add category field
        </button>
        {pickerOpen && (
          <div className="absolute z-20 mt-1 w-full bg-base-100 border border-base-300/50 rounded-box shadow-lg p-2 max-h-72 overflow-y-auto">
            {FIELD_TYPE_GROUPS.map((g) => (
              <div key={g.group} className="mb-1.5">
                <div className="text-[10px] uppercase tracking-wider text-base-content/35 px-1 mb-0.5">{g.group}</div>
                <div className="grid grid-cols-2 gap-1">
                  {g.types.filter((t) => t !== 'computed').map((t) => {
                    const Icon = FIELD_TYPE_META[t].icon;
                    return (
                      <button key={t} onClick={() => add(t)} className="btn btn-ghost btn-xs justify-start gap-1.5 text-[12px]">
                        <Icon size={13} className="text-base-content/40" /> {FIELD_TYPE_META[t].label}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="flex justify-end mt-4 pt-3 border-t border-base-300/20">
        <button onClick={() => saveMutation.mutate()} disabled={!canSave || saveMutation.isPending} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Check size={13} />} Save category fields
        </button>
      </div>
    </div>
  );
}
