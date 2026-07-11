import { useMemo, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  BookMarked, Search, Trash2, Pencil, Download, Upload, Loader2, Check, X, Lock, Layers, Boxes,
} from 'lucide-react';
import { library, globalSections, LIBRARY_KINDS, type LibraryItem } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

/**
 * The Library manager (Builder Experience P1). Browse / search / filter,
 * rename & recategorize & retag, delete, and import/export single items as
 * validated JSON. Insertion into a page happens from the editor's Insert
 * browser; this page is the catalogue.
 */
export default function LibraryPage() {
  const { siteId = '' } = useParams();
  const qc = useQueryClient();
  const { toast } = useToast();

  const [q, setQ] = useState('');
  const [kind, setKind] = useState<string>('');
  const [editing, setEditing] = useState<LibraryItem | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const { data: items = [], isLoading } = useQuery({
    queryKey: ['library', siteId, q, kind],
    queryFn: () => library.list(siteId, { q: q || undefined, kind: kind || undefined }).then((r) => r.data.data as LibraryItem[]),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ['library', siteId] });

  const remove = useMutation({
    mutationFn: (id: string) => library.remove(siteId, id),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Deleted.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const importItem = useMutation({
    mutationFn: (payload: any) => library.import(siteId, payload),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Imported into the Library.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const promote = useMutation({
    mutationFn: (id: string) => globalSections.promote(siteId, id),
    onSuccess: () => toast({ type: 'success', message: 'Promoted to a global section — manage it under Global Sections.' }),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const categories = useMemo(
    () => Array.from(new Set(items.map((i) => i.category).filter(Boolean))).sort(),
    [items],
  );

  const exportItem = async (item: LibraryItem) => {
    // Pull the full record (blocks_data may be omitted from list payloads someday).
    const full = (await library.get(siteId, item.id)).data.data as LibraryItem;
    const payload = {
      name: full.name, kind: full.kind, category: full.category,
      tags: full.tags ?? [], description: full.description, blocks_data: full.blocks_data,
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${full.slug || full.name.replace(/\s+/g, '-').toLowerCase()}.library.json`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const onImportFile = async (file: File) => {
    try {
      const parsed = JSON.parse(await file.text());
      if (!parsed?.blocks_data || !Array.isArray(parsed.blocks_data)) {
        toast({ type: 'error', message: 'That file is not a Library item (no blocks_data array).' });
        return;
      }
      importItem.mutate({
        name: parsed.name || file.name.replace(/\.library\.json$|\.json$/i, ''),
        kind: parsed.kind, category: parsed.category, tags: parsed.tags, description: parsed.description,
        blocks_data: parsed.blocks_data,
      });
    } catch {
      toast({ type: 'error', message: 'Could not read that file as JSON.' });
    }
  };

  return (
    <div className="max-w-5xl mx-auto py-8 px-2">
      <div className="flex items-center justify-between mb-1">
        <div className="flex items-center gap-2">
          <BookMarked className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Library</h1>
        </div>
        <div>
          <input ref={fileRef} type="file" accept="application/json,.json" className="hidden"
            onChange={(e) => { const f = e.target.files?.[0]; if (f) onImportFile(f); e.target.value = ''; }} />
          <button onClick={() => fileRef.current?.click()} disabled={importItem.isPending}
            className="btn btn-outline btn-sm gap-1.5">
            {importItem.isPending ? <Loader2 size={14} className="animate-spin" /> : <Upload size={14} />}
            Import
          </button>
        </div>
      </div>
      <p className="text-sm text-base-content/50 mb-6">
        Reusable sections, rows and compositions. Save from any editor, then insert them into pages.
      </p>

      {/* filter bar */}
      <div className="flex flex-wrap items-center gap-2 mb-5">
        <div className="relative flex-1 min-w-[200px]">
          <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/40" />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name or description…"
            className="input input-bordered input-sm w-full pl-8 text-[13px]" />
        </div>
        <div className="join">
          <button onClick={() => setKind('')} className={`btn btn-xs join-item ${kind === '' ? 'btn-primary' : 'btn-ghost'}`}>All</button>
          {LIBRARY_KINDS.map((k) => (
            <button key={k} onClick={() => setKind(k)} className={`btn btn-xs join-item ${kind === k ? 'btn-primary' : 'btn-ghost'}`}>{k}</button>
          ))}
        </div>
      </div>

      {isLoading && <div className="flex items-center gap-2 text-sm text-base-content/50 py-12 justify-center"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>}

      {!isLoading && items.length === 0 && (
        <div className="text-center py-16 text-base-content/50">
          <Layers className="h-10 w-10 mx-auto mb-3 opacity-30" />
          <p className="text-sm">Nothing here yet. Save a section from the editor, or import a Library item.</p>
        </div>
      )}

      {!isLoading && items.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {items.map((item) => (
            <LibraryCard key={item.id} item={item}
              onEdit={() => setEditing(item)}
              onDelete={() => { if (confirm(`Delete “${item.name}”?`)) remove.mutate(item.id); }}
              onExport={() => exportItem(item)}
              onPromote={() => promote.mutate(item.id)} />
          ))}
        </div>
      )}

      {editing && (
        <EditItemDialog siteId={siteId} item={editing} categories={categories}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); invalidate(); }} />
      )}
    </div>
  );
}

function LibraryCard({ item, onEdit, onDelete, onExport, onPromote }: {
  item: LibraryItem; onEdit: () => void; onDelete: () => void; onExport: () => void; onPromote: () => void;
}) {
  const blockCount = countBlocks(item.blocks_data);
  return (
    <div className="border border-base-300 bg-base-100 flex flex-col">
      <div className="aspect-[16/10] bg-base-200 border-b border-base-300 flex items-center justify-center overflow-hidden">
        {item.preview_image
          ? <img src={item.preview_image} alt="" className="w-full h-full object-cover object-top" />
          : <div className="text-center text-base-content/40"><Layers className="h-6 w-6 mx-auto mb-1" /><span className="text-[11px]">{blockCount} block{blockCount === 1 ? '' : 's'}</span></div>}
      </div>
      <div className="p-3 flex-1 flex flex-col">
        <div className="flex items-start justify-between gap-2">
          <h3 className="text-[13px] font-semibold text-base-content leading-tight truncate">{item.name}</h3>
          {item.is_system && <span className="badge badge-ghost badge-xs gap-1 shrink-0"><Lock size={9} /> system</span>}
        </div>
        <div className="mt-1.5 flex flex-wrap gap-1">
          {item.kind && <span className="badge badge-outline badge-xs">{item.kind}</span>}
          {item.category && <span className="badge badge-ghost badge-xs">{item.category}</span>}
          {(item.tags ?? []).slice(0, 3).map((t) => <span key={t} className="badge badge-ghost badge-xs opacity-70">{t}</span>)}
        </div>
        <div className="mt-auto pt-3 flex items-center gap-1">
          <button onClick={onExport} className="btn btn-ghost btn-xs gap-1" title="Export as JSON"><Download size={12} /></button>
          {(item.kind === 'section' || !item.kind) && (
            <button onClick={onPromote} className="btn btn-ghost btn-xs gap-1 text-primary" title="Promote to a Global Section"><Boxes size={12} /></button>
          )}
          {!item.is_system && (
            <>
              <button onClick={onEdit} className="btn btn-ghost btn-xs gap-1" title="Edit details"><Pencil size={12} /></button>
              <button onClick={onDelete} className="btn btn-ghost btn-xs gap-1 text-error hover:bg-error/10 ml-auto" title="Delete"><Trash2 size={12} /></button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function EditItemDialog({ siteId, item, categories, onClose, onSaved }: {
  siteId: string; item: LibraryItem; categories: string[]; onClose: () => void; onSaved: () => void;
}) {
  const { toast } = useToast();
  const [name, setName] = useState(item.name);
  const [kind, setKind] = useState(item.kind ?? 'section');
  const [category, setCategory] = useState(item.category);
  const [tagsText, setTagsText] = useState((item.tags ?? []).join(', '));

  const save = useMutation({
    mutationFn: () => library.update(siteId, item.id, {
      name: name.trim(), kind, category: category.trim() || 'custom',
      tags: tagsText.split(',').map((t) => t.trim()).filter(Boolean).slice(0, 12),
    }),
    onSuccess: () => { toast({ type: 'success', message: 'Updated.' }); onSaved(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 w-full max-w-md shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-4 py-3 border-b border-base-300">
          <h3 className="text-sm font-semibold text-base-content">Edit library item</h3>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>
        <div className="p-4 space-y-3">
          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1 block">Name</label>
            <input value={name} onChange={(e) => setName(e.target.value)} className="input input-bordered input-sm w-full text-[13px]" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Kind</label>
              <select value={kind} onChange={(e) => setKind(e.target.value)} className="select select-bordered select-sm w-full text-[13px]">
                {LIBRARY_KINDS.map((k) => <option key={k} value={k}>{k}</option>)}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Category</label>
              <input value={category} onChange={(e) => setCategory(e.target.value)} list="lib-cats" className="input input-bordered input-sm w-full text-[13px]" />
              <datalist id="lib-cats">{categories.map((c) => <option key={c} value={c} />)}</datalist>
            </div>
          </div>
          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1 block">Tags <span className="opacity-50">(comma-separated)</span></label>
            <input value={tagsText} onChange={(e) => setTagsText(e.target.value)} className="input input-bordered input-sm w-full text-[13px]" />
          </div>
        </div>
        <div className="flex justify-end gap-2 px-4 py-3 border-t border-base-300">
          <button onClick={onClose} className="btn btn-ghost btn-sm">Cancel</button>
          <button onClick={() => save.mutate()} disabled={!name.trim() || save.isPending} className="btn btn-primary btn-sm gap-1.5">
            {save.isPending ? <Loader2 size={14} className="animate-spin" /> : <Check size={14} />} Save
          </button>
        </div>
      </div>
    </div>
  );
}

function countBlocks(blocks: any[]): number {
  if (!Array.isArray(blocks)) return 0;
  return blocks.reduce((n, b) => n + 1 + countBlocks(b?.children ?? []), 0);
}
