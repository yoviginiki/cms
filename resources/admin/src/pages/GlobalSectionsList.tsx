import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Boxes, BookMarked, Rocket, Undo2, Trash2, Loader2, X, Check } from 'lucide-react';
import { globalSections, library, type GlobalSectionSummary, type LibraryItem } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

/**
 * Global Sections manager (Builder Experience P2). Sections reused across pages
 * by reference. Build content in any editor → save to Library → promote here →
 * embed on pages via the Global Section block. Publish cascades to every
 * embedding page through the stale-republish engine.
 */
export default function GlobalSectionsList() {
  const { siteId = '' } = useParams();
  const qc = useQueryClient();
  const { toast } = useToast();
  const [promoting, setPromoting] = useState(false);

  const { data: items = [], isLoading } = useQuery<GlobalSectionSummary[]>({
    queryKey: ['global-sections', siteId],
    queryFn: () => globalSections.list(siteId).then((r) => r.data.data),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ['global-sections', siteId] });

  const publish = useMutation({
    mutationFn: (id: string) => globalSections.publish(siteId, id),
    onSuccess: (r: any) => {
      invalidate();
      const n = r.data?.meta?.stale?.pages ?? 0;
      toast({ type: 'success', message: n > 0 ? `Published — ${n} page(s) flagged for republish.` : 'Published.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const unpublish = useMutation({
    mutationFn: (id: string) => globalSections.unpublish(siteId, id),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Unpublished.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const remove = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => globalSections.delete(siteId, id, force),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Deleted.' }); },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const n = e.response.data?.usedOnCount ?? 0;
        if (confirm(`This section is embedded on ${n} page(s). Delete anyway? Those pages will lose it.`)) {
          remove.mutate({ id: vars.id, force: true });
        }
      } else {
        toast({ type: 'error', message: apiErr(e) });
      }
    },
  });

  return (
    <div className="max-w-4xl mx-auto py-8 px-2">
      <div className="flex items-center justify-between mb-1">
        <div className="flex items-center gap-2">
          <Boxes className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Global Sections</h1>
        </div>
        <button onClick={() => setPromoting(true)} className="btn btn-primary btn-sm gap-1.5">
          <BookMarked size={14} /> New from Library
        </button>
      </div>
      <p className="text-sm text-base-content/50 mb-6">
        Edit once, updates everywhere. Embed a global on any page with the “Global Section” block;
        publishing flags every embedding page for republish.
      </p>

      {isLoading && <div className="flex items-center gap-2 text-sm text-base-content/50 py-12 justify-center"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>}

      {!isLoading && items.length === 0 && (
        <div className="text-center py-16 text-base-content/50">
          <Boxes className="h-10 w-10 mx-auto mb-3 opacity-30" />
          <p className="text-sm">No global sections yet. Save a section to the Library, then promote it here.</p>
        </div>
      )}

      {!isLoading && items.length > 0 && (
        <div className="border border-base-300 divide-y divide-base-300 bg-base-100">
          {items.map((s) => (
            <div key={s.id} className="flex items-center gap-3 px-4 py-3">
              <Boxes size={16} className="text-primary/60 shrink-0" />
              <div className="min-w-0 flex-1">
                <div className="text-[13px] font-medium text-base-content truncate">{s.name}</div>
                <div className="flex items-center gap-2 mt-0.5">
                  <span className={`badge badge-xs ${s.status === 'published' ? 'badge-success' : 'badge-ghost'}`}>{s.status}</span>
                  <span className="text-[11px] text-base-content/40">embedded on {s.used_on} page{s.used_on === 1 ? '' : 's'}</span>
                </div>
              </div>
              {s.status === 'published' ? (
                <button onClick={() => unpublish.mutate(s.id)} className="btn btn-ghost btn-xs gap-1" title="Unpublish"><Undo2 size={12} /> Unpublish</button>
              ) : (
                <button onClick={() => publish.mutate(s.id)} className="btn btn-ghost btn-xs gap-1 text-primary" title="Publish"><Rocket size={12} /> Publish</button>
              )}
              <button onClick={() => remove.mutate({ id: s.id })} className="btn btn-ghost btn-xs text-error hover:bg-error/10" title="Delete"><Trash2 size={12} /></button>
            </div>
          ))}
        </div>
      )}

      {promoting && (
        <PromoteDialog siteId={siteId} onClose={() => setPromoting(false)}
          onPromoted={() => { setPromoting(false); invalidate(); toast({ type: 'success', message: 'Promoted to a global section.' }); }} />
      )}
    </div>
  );
}

/** Pick a Library item to promote into a new global section. */
function PromoteDialog({ siteId, onClose, onPromoted }: { siteId: string; onClose: () => void; onPromoted: () => void }) {
  const { toast } = useToast();
  const [selected, setSelected] = useState<string | null>(null);

  const { data: items = [], isLoading } = useQuery<LibraryItem[]>({
    queryKey: ['library', siteId, 'promote'],
    queryFn: () => library.list(siteId, { kind: 'section' }).then((r) => r.data.data),
  });

  const promote = useMutation({
    mutationFn: (id: string) => globalSections.promote(siteId, id),
    onSuccess: onPromoted,
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 w-full max-w-md shadow-xl flex flex-col max-h-[80vh]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-4 py-3 border-b border-base-300">
          <h3 className="text-sm font-semibold text-base-content">Promote a Library item to Global</h3>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>
        <div className="flex-1 overflow-y-auto p-3">
          {isLoading && <div className="text-center text-sm text-base-content/50 py-8"><Loader2 className="h-4 w-4 animate-spin inline" /></div>}
          {!isLoading && items.length === 0 && (
            <p className="text-[13px] text-base-content/50 text-center py-8">No section-kind Library items yet. Save a section to the Library first.</p>
          )}
          <div className="space-y-1">
            {items.map((it) => (
              <button key={it.id} onClick={() => setSelected(it.id)}
                className={`w-full text-left px-3 py-2 border text-[13px] flex items-center justify-between ${selected === it.id ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary/40'}`}>
                <span className="truncate">{it.name}<span className="text-base-content/40 ml-2 text-[11px]">{it.category}</span></span>
                {selected === it.id && <Check size={14} className="text-primary shrink-0" />}
              </button>
            ))}
          </div>
        </div>
        <div className="flex justify-end gap-2 px-4 py-3 border-t border-base-300">
          <button onClick={onClose} className="btn btn-ghost btn-sm">Cancel</button>
          <button onClick={() => selected && promote.mutate(selected)} disabled={!selected || promote.isPending}
            className="btn btn-primary btn-sm gap-1.5">
            {promote.isPending ? <Loader2 size={14} className="animate-spin" /> : <Boxes size={14} />} Promote
          </button>
        </div>
      </div>
    </div>
  );
}
