import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Trash2, Loader2, GalleryHorizontalEnd, Copy, Edit2, Rocket } from 'lucide-react';
import { sliders } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useToast } from '@/components/ui/Toast';

interface SliderSummary {
  id: string;
  name: string;
  status: string;
  published_at: string | null;
  updated_at: string;
  used_on: number;
}

export default function SlidersList() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [deleteTarget, setDeleteTarget] = useState<SliderSummary | null>(null);
  const [forceTarget, setForceTarget] = useState<{ slider: SliderSummary; count: number; sources: { title: string }[] } | null>(null);

  const { data, isLoading, error } = useQuery<SliderSummary[]>({
    queryKey: ['sliders', siteId],
    queryFn: () => sliders.list(siteId).then(r => r.data.data),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['sliders', siteId] });

  const createMutation = useMutation({
    mutationFn: (name: string) => sliders.create(siteId, name),
    onSuccess: (r) => { invalidate(); navigate(`/sites/${siteId}/sliders/${r.data.data.id}/edit`); },
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message || 'Failed to create slider' }),
  });

  const duplicateMutation = useMutation({
    mutationFn: (id: string) => sliders.duplicate(siteId, id),
    onSuccess: invalidate,
  });

  const publishMutation = useMutation({
    mutationFn: (id: string) => sliders.publish(siteId, id),
    onSuccess: (r) => {
      invalidate();
      queryClient.invalidateQueries({ queryKey: ['stale-count', siteId] });
      const stale = r.data.meta?.stale;
      const n = (stale?.pages ?? 0) + (stale?.posts ?? 0);
      toast({ type: n > 0 ? 'info' : 'success',
        message: n > 0 ? `Slider published — ${n} page(s) affected. Review & republish from “Stale pages”.` : 'Slider published.' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => sliders.delete(siteId, id, force),
    onSuccess: () => { invalidate(); setDeleteTarget(null); setForceTarget(null); },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const slider = data?.find(s => s.id === vars.id) ?? deleteTarget;
        if (slider) setForceTarget({ slider, count: e.response.data?.usedOnCount ?? 0, sources: e.response.data?.sources ?? [] });
        setDeleteTarget(null);
      } else {
        toast({ type: 'error', message: e?.response?.data?.message || 'Failed to delete slider' });
      }
    },
  });

  const handleCreate = () => {
    const name = window.prompt('Slider name:');
    if (name?.trim()) createMutation.mutate(name.trim());
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Sliders</h1>
          <p className="mt-1 text-sm text-base-content/50">Library of animated sliders — embed them into pages with the Slider block</p>
        </div>
        <button onClick={handleCreate} disabled={createMutation.isPending}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
          New Slider
        </button>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load sliders.</div>}
      {data && data.length === 0 && (
        <EmptyState icon={GalleryHorizontalEnd} title="No sliders yet" description="Create a slider, design its slides, then embed it into any page" actionLabel="New Slider" onAction={handleCreate} />
      )}

      {data && data.length > 0 && (
        <div className="bg-base-100 rounded-xl border border-base-300 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-base-300 bg-base-200">
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Name</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Status</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Used on</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Updated</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.map((s) => (
                <tr key={s.id} className="hover:bg-base-200 transition-colors">
                  <td className="px-6 py-4">
                    <button onClick={() => navigate(`/sites/${siteId}/sliders/${s.id}/edit`)}
                      className="font-medium text-base-content hover:text-blue-600 hover:underline">{s.name}</button>
                  </td>
                  <td className="px-6 py-4"><StatusBadge status={s.status} /></td>
                  <td className="px-6 py-4 text-sm text-base-content/60">{s.used_on} page{s.used_on === 1 ? '' : 's'}</td>
                  <td className="px-6 py-4 text-sm text-base-content/50">{new Date(s.updated_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => publishMutation.mutate(s.id)} disabled={publishMutation.isPending}
                        className="p-2 text-base-content/40 hover:text-green-600 rounded-lg hover:bg-green-50" title="Publish">
                        <Rocket className="h-4 w-4" />
                      </button>
                      <button onClick={() => navigate(`/sites/${siteId}/sliders/${s.id}/edit`)}
                        className="p-2 text-base-content/40 hover:text-blue-600 rounded-lg hover:bg-blue-50" title="Edit">
                        <Edit2 className="h-4 w-4" />
                      </button>
                      <button onClick={() => duplicateMutation.mutate(s.id)} disabled={duplicateMutation.isPending}
                        className="p-2 text-base-content/40 hover:text-green-600 rounded-lg hover:bg-green-50" title="Duplicate">
                        <Copy className="h-4 w-4" />
                      </button>
                      <button onClick={() => setDeleteTarget(s)}
                        className="p-2 text-base-content/40 hover:text-red-600 rounded-lg hover:bg-red-50" title="Delete">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete slider"
        message={`Delete "${deleteTarget?.name}"? Pages embedding it will show nothing until edited.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id })}
        onClose={() => setDeleteTarget(null)}
      />

      {/* Generic delete protection: referenced sliders need explicit force */}
      <ConfirmDialog
        open={!!forceTarget}
        title="Slider is still in use"
        message={`"${forceTarget?.slider.name}" is embedded on ${forceTarget?.count} page(s): ${forceTarget?.sources.slice(0, 8).map(s => s.title).join(', ')}${(forceTarget?.sources.length ?? 0) > 8 ? '…' : ''}. Deleting it will leave gaps (affected pages get flagged for republish). Delete anyway?`}
        confirmText="Force delete"
        variant="danger"
        onConfirm={() => forceTarget && deleteMutation.mutate({ id: forceTarget.slider.id, force: true })}
        onClose={() => setForceTarget(null)}
      />
    </div>
  );
}
