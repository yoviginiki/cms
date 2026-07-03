import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { AlertTriangle, CheckCircle2, Loader2, RefreshCw, Rocket, FileWarning } from 'lucide-react';
import { staleContent, publishing } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { useToast } from '@/components/ui/Toast';

interface StaleItem {
  id: string;
  title: string;
  slug: string;
  status: string;
  needs_republish_reason: string | null;
  updated_at: string;
}

interface StagedBatch {
  id: string;
  status: string;
  metadata: {
    built?: { type: string; id: string; title: string; path: string }[];
    failed?: { type: string; id: string; title: string; error: string }[];
    pages_total?: number;
  };
}

interface StaleData {
  pages: StaleItem[];
  posts: StaleItem[];
  site_stale: { flag: boolean; reason: string; at: string } | null;
  count: number;
  staged_batch: StagedBatch | null;
}

export default function StalePages() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [fullPublishing, setFullPublishing] = useState(false);

  const { data, isLoading, error } = useQuery<StaleData>({
    queryKey: ['stale', siteId],
    queryFn: () => staleContent.list(siteId).then(r => r.data.data),
    // Poll while a batch is queued/building so the staged panel appears on its own
    refetchInterval: (query) => {
      const status = query.state.data?.staged_batch?.status;
      return status === 'queued' || status === 'building' ? 2000 : false;
    },
  });

  const allItems = [
    ...(data?.pages ?? []).map(p => ({ ...p, type: 'page' as const })),
    ...(data?.posts ?? []).map(p => ({ ...p, type: 'post' as const })),
  ];
  const key = (item: { type: string; id: string }) => `${item.type}:${item.id}`;
  const allSelected = allItems.length > 0 && allItems.every(i => selected.has(key(i)));

  const toggleAll = () => {
    setSelected(allSelected ? new Set() : new Set(allItems.map(key)));
  };
  const toggle = (k: string) => {
    setSelected(prev => {
      const next = new Set(prev);
      next.has(k) ? next.delete(k) : next.add(k);
      return next;
    });
  };

  const republishMutation = useMutation({
    mutationFn: () => staleContent.republish(siteId, {
      page_ids: allItems.filter(i => i.type === 'page' && selected.has(key(i))).map(i => i.id),
      post_ids: allItems.filter(i => i.type === 'post' && selected.has(key(i))).map(i => i.id),
    }),
    onSuccess: () => {
      toast({ type: 'info', message: 'Staged rebuild started — nothing goes live until you promote.' });
      setSelected(new Set());
      queryClient.invalidateQueries({ queryKey: ['stale', siteId] });
    },
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message || 'Failed to start republish' }),
  });

  const promoteMutation = useMutation({
    mutationFn: (deploymentId: string) => staleContent.promote(siteId, deploymentId),
    onSuccess: (r) => {
      const promoted = r.data.data?.promoted ?? 0;
      toast({ type: 'success', message: `Promoted ${promoted} page(s) to live.` });
      queryClient.invalidateQueries({ queryKey: ['stale', siteId] });
    },
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message || 'Promote failed' }),
  });

  const fullPublish = async () => {
    setFullPublishing(true);
    try {
      await publishing.publish(siteId, 'full');
      toast({ type: 'info', message: 'Full site rebuild started — staleness clears when it completes.' });
    } catch (e: any) {
      toast({ type: 'error', message: e?.response?.data?.message || 'Publish failed' });
    } finally {
      setFullPublishing(false);
    }
  };

  const batch = data?.staged_batch;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Stale pages</h1>
          <p className="mt-1 text-sm text-base-content/50">
            Published pages whose embedded content changed after they were built
          </p>
        </div>
        <button
          onClick={() => republishMutation.mutate()}
          disabled={selected.size === 0 || republishMutation.isPending}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {republishMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
          Republish selected (staged)
        </button>
      </div>

      {/* Site-wide staleness banner: theme/menu changes need a full rebuild */}
      {data?.site_stale && (
        <div className="alert alert-warning mb-6 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5" />
            <div>
              <div className="font-medium">All published pages are stale</div>
              <div className="text-sm opacity-80">{data.site_stale.reason}</div>
            </div>
          </div>
          <button onClick={fullPublish} disabled={fullPublishing}
            className="btn btn-sm btn-neutral">
            {fullPublishing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
            Rebuild entire site
          </button>
        </div>
      )}

      {/* Staged batch awaiting promotion */}
      {batch && (
        <div className="bg-base-100 rounded-xl border border-warning/40 shadow-sm p-4 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Rocket className="h-5 w-5 text-warning" />
              <span className="font-medium">Staged batch</span>
              <StatusBadge status={batch.status} />
              {(batch.status === 'queued' || batch.status === 'building') && (
                <Loader2 className="h-4 w-4 animate-spin text-base-content/40" />
              )}
            </div>
            {batch.status === 'staged' && (
              <button
                onClick={() => promoteMutation.mutate(batch.id)}
                disabled={promoteMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 disabled:opacity-50">
                {promoteMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                Promote to live
              </button>
            )}
          </div>
          {batch.status === 'staged' && (
            <div className="mt-3 text-sm text-base-content/60 space-y-1">
              <div>{batch.metadata.built?.length ?? 0} page(s) rebuilt and staged. Review, then promote — nothing is live yet.</div>
              {(batch.metadata.failed?.length ?? 0) > 0 && (
                <div className="text-error">
                  {batch.metadata.failed!.length} failed:
                  {batch.metadata.failed!.map(f => ` ${f.title} (${f.error})`).join(';')}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load stale content.</div>}
      {data && allItems.length === 0 && !data.site_stale && (
        <EmptyState icon={FileWarning} title="Nothing is stale" description="All published pages are up to date with their referenced content" />
      )}

      {allItems.length > 0 && (
        <div className="bg-base-100 rounded-xl border border-base-300 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-base-300 bg-base-200">
                <th className="px-4 py-3 w-10">
                  <input type="checkbox" className="checkbox checkbox-sm" checked={allSelected} onChange={toggleAll} />
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Title</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Type</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Reason</th>
                <th className="text-left px-4 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {allItems.map((item) => (
                <tr key={key(item)} className="hover:bg-base-200 transition-colors">
                  <td className="px-4 py-3">
                    <input type="checkbox" className="checkbox checkbox-sm"
                      checked={selected.has(key(item))} onChange={() => toggle(key(item))} />
                  </td>
                  <td className="px-4 py-3">
                    <div className="font-medium text-base-content">{item.title}</div>
                    <div className="text-[11px] text-base-content/40 font-mono">/{item.slug}</div>
                  </td>
                  <td className="px-4 py-3 text-sm text-base-content/60 capitalize">{item.type}</td>
                  <td className="px-4 py-3 text-sm text-base-content/70">
                    <span className="inline-flex items-center gap-1.5">
                      <AlertTriangle className="h-3.5 w-3.5 text-warning shrink-0" />
                      {item.needs_republish_reason || 'Referenced content changed'}
                    </span>
                  </td>
                  <td className="px-4 py-3"><StatusBadge status={item.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
