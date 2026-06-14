import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Edit, Trash2, FileText, Loader2, Home, RefreshCw, Eraser } from 'lucide-react';
import { pages, sites, publishing } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Page {
  id: string;
  title: string;
  slug: string;
  status: string;
  updated_at: string;
}

interface SiteData {
  settings: Record<string, unknown>;
}

export default function PagesList() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<Page | null>(null);
  const [publishing_, setPublishing] = useState(false);
  const [clearing, setClearing] = useState(false);

  const { data, isLoading, error } = useQuery<Page[]>({
    queryKey: ['pages', siteId],
    queryFn: () => pages.list(siteId).then(r => r.data.data),
  });

  const { data: siteData } = useQuery<SiteData>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then(r => r.data.data),
  });

  const homepageId = (siteData?.settings?.homepage_id as string) || '';
  const isHomepage = (page: Page) => homepageId ? page.id === homepageId : page.slug === 'home';

  const deleteMutation = useMutation({
    mutationFn: (pageId: string) => pages.delete(siteId, pageId),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['pages', siteId] }); setDeleteTarget(null); },
  });

  const createMutation = useMutation({
    mutationFn: (title: string) => pages.create(siteId, { title, status: 'draft' }),
    onSuccess: (r) => navigate(`/sites/${siteId}/pages/${r.data.data.id}/edit`),
    onError: (e: any) => alert(e?.response?.data?.message || 'Failed to create page'),
  });

  const handleCreate = () => {
    const title = window.prompt('Page title:');
    if (title?.trim()) createMutation.mutate(title.trim());
  };

  const sortedPages = data ? [...data].sort((a, b) => {
    if (isHomepage(a)) return -1;
    if (isHomepage(b)) return 1;
    return a.title.localeCompare(b.title);
  }) : [];

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Pages</h1>
          <p className="mt-1 text-sm text-base-content/50">Manage your site pages</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={async () => {
              if (!confirm('Clear all published files? The public site will go offline until you rebuild.')) return;
              setClearing(true);
              try { await publishing.clear(siteId); } catch {} finally { setClearing(false); }
            }}
            disabled={clearing}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-base-300 text-base-content/70 hover:bg-base-200 disabled:opacity-50"
            title="Clear all published static files">
            {clearing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Eraser className="h-3.5 w-3.5" />}
            Clear
          </button>
          <button
            onClick={async () => {
              setPublishing(true);
              try { await publishing.publish(siteId, 'full'); } catch {} finally { setPublishing(false); }
            }}
            disabled={publishing_}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-50 disabled:opacity-50"
            title="Rebuild entire site from scratch">
            {publishing_ ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
            Rebuild Site
          </button>
          <button onClick={handleCreate} disabled={createMutation.isPending}
            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
            Create Page
          </button>
        </div>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load pages.</div>}
      {data && data.length === 0 && (
        <EmptyState icon={FileText} title="No pages yet" description="Create your first page" actionLabel="Create Page" onAction={handleCreate} />
      )}

      {/* Mobile card view */}
      {sortedPages.length > 0 && (
        <div className="lg:hidden space-y-2">
          {sortedPages.map((page) => (
            <div key={page.id} className={`bg-base-100 rounded-lg border p-3 ${isHomepage(page) ? 'border-blue-200 bg-blue-50/30' : 'border-base-300'}`}>
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <button onClick={() => navigate(`/sites/${siteId}/pages/${page.id}/edit`)}
                    className="text-sm font-medium text-base-content hover:text-blue-600 text-left truncate block w-full">
                    {page.title}
                    {isHomepage(page) && <span className="ml-1.5 text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Front Page</span>}
                  </button>
                  <div className="flex items-center gap-2 mt-1">
                    <StatusBadge status={page.status} />
                    <span className="text-[11px] text-base-content/40 font-mono">/{page.slug}</span>
                  </div>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <button onClick={() => navigate(`/sites/${siteId}/pages/${page.id}/edit`)}
                    className="p-2 text-base-content/40 hover:text-blue-600 rounded-lg" title="Edit">
                    <Edit className="h-4 w-4" />
                  </button>
                  <button onClick={() => setDeleteTarget(page)}
                    className="p-2 text-base-content/40 hover:text-red-600 rounded-lg" title="Delete">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Desktop table view */}
      {sortedPages.length > 0 && (
        <div className="bg-base-100 rounded-xl border border-base-300 shadow-sm overflow-hidden hidden lg:block">
          <table className="w-full">
            <thead>
              <tr className="border-b border-base-300 bg-base-200">
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Title</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">URL</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Status</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Updated</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-base-content/50 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {sortedPages.map((page) => (
                <tr key={page.id} className={`hover:bg-base-200 transition-colors ${isHomepage(page) ? 'bg-blue-50/50' : ''}`}>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <button onClick={() => navigate(`/sites/${siteId}/pages/${page.id}/edit`)}
                        className="font-medium text-base-content hover:text-blue-600 hover:underline text-left cursor-pointer">{page.title}</button>
                      {isHomepage(page) && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                          <Home className="h-3 w-3" /> Front Page
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-6 py-4 text-sm text-base-content/50 font-mono">
                    {isHomepage(page) ? <span className="text-blue-600 font-medium">/</span> : <>/{page.slug}</>}
                  </td>
                  <td className="px-6 py-4"><StatusBadge status={page.status} /></td>
                  <td className="px-6 py-4 text-sm text-base-content/50">{new Date(page.updated_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => navigate(`/sites/${siteId}/pages/${page.id}/edit`)}
                        className="p-2 text-base-content/40 hover:text-blue-600 rounded-lg hover:bg-blue-50" title="Edit">
                        <Edit className="h-4 w-4" />
                      </button>
                      <button onClick={() => setDeleteTarget(page)}
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
        title="Delete page"
        message={`Are you sure you want to delete "${deleteTarget?.title}"?`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
