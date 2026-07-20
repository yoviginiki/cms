import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Plus, Loader2, Pencil, Trash2, ListFilter, Globe, Code2 } from 'lucide-react';
import { savedQueries, type SavedQuery } from '@/lib/api';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useToast } from '@/components/ui/Toast';

/** Track G-Q3 — saved queries: reusable filters/aggregations for blocks & the public API. */
export default function QueriesList() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [deleteTarget, setDeleteTarget] = useState<SavedQuery | null>(null);
  const [forceDelete, setForceDelete] = useState<{ query: SavedQuery; count: number } | null>(null);

  const { data: queries = [], isLoading, error } = useQuery<SavedQuery[]>({
    queryKey: ['saved-queries', siteId],
    queryFn: () => savedQueries.list(siteId).then((r) => r.data.data),
  });

  const deleteMutation = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => savedQueries.delete(siteId, id, force),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saved-queries', siteId] });
      setDeleteTarget(null);
      setForceDelete(null);
      toast({ type: 'success', message: 'Query deleted.' });
    },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const q = queries.find((x) => x.id === vars.id) ?? deleteTarget;
        if (q) setForceDelete({ query: q, count: e.response.data?.usedOnCount ?? 0 });
        setDeleteTarget(null);
      } else {
        toast({ type: 'error', message: e?.response?.data?.message ?? 'Delete failed.' });
        setDeleteTarget(null);
      }
    },
  });

  if (isLoading) return <div className="flex justify-center py-20"><Loader2 className="animate-spin text-base-content/30" /></div>;
  if (error) return <div className="alert alert-error text-sm">Could not load queries.</div>;

  return (
    <div className="max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-5">
        <div>
          <h1 className="text-xl font-semibold flex items-center gap-2"><ListFilter size={20} /> Queries</h1>
          <p className="text-[13px] text-base-content/50 mt-0.5">
            Saved filters and aggregations over your collections — render them with the Query Table, Query Stat and Record Loop blocks.
          </p>
        </div>
        <Link to={`/sites/${siteId}/queries/new`} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          <Plus size={14} /> New query
        </Link>
      </div>

      {queries.length === 0 ? (
        <div className="border border-dashed border-base-300/60 rounded-box py-16 text-center">
          <p className="text-[13px] text-base-content/40 mb-3">No saved queries yet.</p>
          <Link to={`/sites/${siteId}/queries/new`} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
            <Plus size={14} /> Build your first query
          </Link>
        </div>
      ) : (
        <div className="border border-base-300/40 rounded-box bg-base-100 divide-y divide-base-300/20">
          {queries.map((q) => (
            <div key={q.id} className="flex items-center gap-3 px-4 py-3">
              <div className="flex-1 min-w-0">
                <Link to={`/sites/${siteId}/queries/${q.id}/edit`} className="text-[13px] font-medium hover:text-primary transition-colors">
                  {q.name}
                </Link>
                <div className="text-[11px] text-base-content/35 flex items-center gap-2 mt-0.5">
                  <span className="font-mono">{q.slug}</span>
                  {q.collection_name && <span>· {q.collection_name}</span>}
                </div>
              </div>
              {q.mode === 'sql'
                ? <span className="badge badge-ghost badge-sm gap-1 text-[11px]"><Code2 size={11} /> SQL</span>
                : <span className="badge badge-ghost badge-sm text-[11px]">Visual</span>}
              {q.is_public && <span className="badge badge-outline badge-sm gap-1 text-[11px]" title="Available on the public API"><Globe size={11} /> public</span>}
              <Link to={`/sites/${siteId}/queries/${q.id}/edit`} className="btn btn-ghost btn-xs btn-square" title="Edit"><Pencil size={13} /></Link>
              <button onClick={() => setDeleteTarget(q)} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Delete">
                <Trash2 size={13} />
              </button>
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete query"
        message={`Delete "${deleteTarget?.name}"? Blocks using it will render empty.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id })}
        onClose={() => setDeleteTarget(null)}
      />
      <ConfirmDialog
        open={!!forceDelete}
        title="Query is still in use"
        message={`"${forceDelete?.query.name}" is used on ${forceDelete?.count} page(s)/block(s). Delete anyway?`}
        confirmText="Delete anyway"
        variant="danger"
        onConfirm={() => forceDelete && deleteMutation.mutate({ id: forceDelete.query.id, force: true })}
        onClose={() => setForceDelete(null)}
      />
    </div>
  );
}
