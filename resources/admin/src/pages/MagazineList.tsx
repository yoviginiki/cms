import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Edit, Trash2, BookOpen, Search, ExternalLink, Paintbrush } from 'lucide-react';
import { magazines, pages as pagesApi, sites } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface MagazineItem {
  id: string;
  title: string;
  slug: string;
  status: string;
  pages_count: number;
  cover_image: string | null;
  updated_at: string;
  published_at: string | null;
}

interface ComposerPage {
  id: string;
  title: string;
  slug: string;
  status: string;
  editor_mode: string;
  updated_at: string;
}

export default function MagazineList() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<MagazineItem | null>(null);
  const [deleteComposerPage, setDeleteComposerPage] = useState<ComposerPage | null>(null);
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery<MagazineItem[]>({
    queryKey: ['magazines', siteId, search],
    queryFn: () => {
      const params: Record<string, unknown> = {};
      if (search) params.search = search;
      return magazines.list(siteId, params).then(r => r.data.data.data ?? r.data.data);
    },
  });

  // Get site's public domain for preview links
  const { data: siteData } = useQuery({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
    enabled: !!siteId,
  });
  const publicDomain = siteData?.custom_domain || siteData?.slug + '.ensodo.eu';
  const publicBase = publicDomain ? `https://${publicDomain}` : '';

  // Also load pages with editor_mode=magazine (from Issue Composer handoffs)
  const { data: composerPages } = useQuery<ComposerPage[]>({
    queryKey: ['composer-pages', siteId],
    queryFn: () => pagesApi.list(siteId, { editor_mode: 'magazine' }).then((r: any) => {
      const raw = r.data.data?.data ?? r.data.data ?? r.data ?? [];
      return (Array.isArray(raw) ? raw : []).filter((p: any) => p.editor_mode === 'magazine' && p.status !== 'archived');
    }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => magazines.delete(siteId, id),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['magazines', siteId] }); setDeleteTarget(null); },
  });

  const deleteComposerMutation = useMutation({
    mutationFn: (id: string) => pagesApi.delete(siteId, id),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['composer-pages', siteId] }); setDeleteComposerPage(null); },
  });

  const createMutation = useMutation({
    mutationFn: (title: string) => magazines.create(siteId, { title }),
    onSuccess: (r) => navigate(`/sites/${siteId}/magazines/${r.data.data.id}/edit`),
  });

  const handleCreate = () => {
    const title = window.prompt('Magazine title:');
    if (title?.trim()) createMutation.mutate(title.trim());
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-lg font-medium text-base-content/90">Magazines</h1>
          <p className="mt-0.5 text-[13px] text-base-content/40">Create and manage flipbook magazines</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/magazine/wizard`)}
            className="btn btn-ghost btn-sm text-[12px] gap-1.5 text-primary">
            AI compose issue
          </button>
          <button onClick={handleCreate} disabled={createMutation.isPending} className="btn btn-primary btn-sm text-[12px] gap-1.5">
            <Plus className="h-3.5 w-3.5" /> New magazine
          </button>
        </div>
      </div>

      <div className="flex items-center gap-2 mb-5">
        <label className="input input-bordered input-sm flex-1 max-w-sm flex items-center gap-2 text-[13px]">
          <Search className="h-3.5 w-3.5 text-base-content/30" />
          <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search magazines..." className="grow bg-transparent" />
        </label>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20"></span>
        </div>
      )}

      {data && data.length === 0 && (
        <EmptyState icon={BookOpen} title="No magazines yet" description="Create your first interactive flipbook magazine" actionLabel="New magazine" onAction={handleCreate} />
      )}

      {data && data.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map(mag => (
            <div key={mag.id} className="card bg-base-100 border border-base-300/40 hover:border-base-300/70 transition-all cursor-pointer"
              onClick={() => navigate(`/sites/${siteId}/magazines/${mag.id}/edit`)}>
              <div className="card-body p-4 gap-3">
                {/* Cover preview */}
                <div className="aspect-[3/4] bg-base-200 rounded-md overflow-hidden flex items-center justify-center">
                  {mag.cover_image ? (
                    <img src={mag.cover_image} alt={mag.title} className="w-full h-full object-cover" />
                  ) : (
                    <BookOpen className="h-10 w-10 text-base-content/10" strokeWidth={1} />
                  )}
                </div>

                <div className="flex items-start justify-between">
                  <div className="min-w-0">
                    <h3 className="text-sm font-medium text-base-content/90 truncate">{mag.title}</h3>
                    <p className="text-[11px] text-base-content/30 mt-0.5">{mag.pages_count ?? 0} pages</p>
                  </div>
                  <StatusBadge status={mag.status} />
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-base-300/20">
                  <span className="text-[11px] text-base-content/30">{new Date(mag.updated_at).toLocaleDateString()}</span>
                  <div className="flex gap-0.5">
                    {mag.status === 'published' && (
                      <a href={`${publicBase}/magazine/${mag.slug}`} target="_blank" rel="noopener" onClick={e => e.stopPropagation()}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-primary" title="View">
                        <ExternalLink className="h-3.5 w-3.5" />
                      </a>
                    )}
                    <button onClick={e => { e.stopPropagation(); navigate(`/sites/${siteId}/magazines/${mag.id}/edit`); }}
                      className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-primary" title="Edit">
                      <Edit className="h-3.5 w-3.5" />
                    </button>
                    <button onClick={e => { e.stopPropagation(); setDeleteTarget(mag); }}
                      className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-error" title="Delete">
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Issue Composer handoff pages */}
      {composerPages && composerPages.length > 0 && (
        <div className="mt-8">
          <h2 className="text-sm font-medium text-base-content/70 mb-3 flex items-center gap-2">
            <Paintbrush size={14} className="text-primary/50" />
            Issue Composer Pages
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {composerPages.map(pg => (
              <div key={pg.id} className="card bg-base-100 border border-primary/20 hover:border-primary/40 transition-all cursor-pointer"
                onClick={() => navigate(`/sites/${siteId}/pages/${pg.id}/edit`)}>
                <div className="card-body p-4 gap-3">
                  <div className="aspect-[3/4] bg-base-200 rounded-md overflow-hidden flex items-center justify-center">
                    <Paintbrush className="h-10 w-10 text-primary/15" strokeWidth={1} />
                  </div>
                  <div className="flex items-start justify-between">
                    <div className="min-w-0">
                      <h3 className="text-sm font-medium text-base-content/90 truncate">{pg.title}</h3>
                      <p className="text-[11px] text-base-content/30 mt-0.5">Canvas editor</p>
                    </div>
                    <StatusBadge status={pg.status} />
                  </div>
                  <div className="flex items-center justify-between pt-2 border-t border-base-300/20">
                    <span className="text-[11px] text-base-content/30">{new Date(pg.updated_at).toLocaleDateString()}</span>
                    <div className="flex gap-0.5">
                      <a href={`${publicBase}/issue/${pg.slug}`} target="_blank" rel="noopener" onClick={e => e.stopPropagation()}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-success" title="Preview">
                        <ExternalLink className="h-3.5 w-3.5" />
                      </a>
                      <button onClick={e => { e.stopPropagation(); navigate(`/sites/${siteId}/pages/${pg.id}/edit`); }}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-primary" title="Edit in canvas">
                        <Edit className="h-3.5 w-3.5" />
                      </button>
                      <button onClick={e => { e.stopPropagation(); setDeleteComposerPage(pg); }}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-error" title="Delete">
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete magazine"
        message={`Are you sure you want to delete "${deleteTarget?.title}"?`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />

      <ConfirmDialog
        open={!!deleteComposerPage}
        title="Delete composer page"
        message={`Are you sure you want to delete "${deleteComposerPage?.title}"? This will remove all magazine pages and elements.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteComposerPage && deleteComposerMutation.mutate(deleteComposerPage.id)}
        onClose={() => setDeleteComposerPage(null)}
      />
    </div>
  );
}
