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
    mutationFn: (title: string) => pages.create(siteId, { title, slug: title.toLowerCase().replace(/\s+/g, '-'), status: 'draft' }),
    onSuccess: (r) => navigate(`/sites/${siteId}/pages/${r.data.data.id}/edit`),
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
          <h1 className="text-2xl font-bold text-gray-900">Pages</h1>
          <p className="mt-1 text-sm text-gray-500">Manage your site pages</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={async () => {
              if (!confirm('Clear all published files? The public site will go offline until you rebuild.')) return;
              setClearing(true);
              try { await publishing.clear(siteId); } catch {} finally { setClearing(false); }
            }}
            disabled={clearing}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-50"
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

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load pages.</div>}
      {data && data.length === 0 && (
        <EmptyState icon={FileText} title="No pages yet" description="Create your first page" actionLabel="Create Page" onAction={handleCreate} />
      )}

      {sortedPages.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50">
                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {sortedPages.map((page) => (
                <tr key={page.id} className={`hover:bg-gray-50 transition-colors ${isHomepage(page) ? 'bg-blue-50/50' : ''}`}>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-gray-900">{page.title}</span>
                      {isHomepage(page) && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                          <Home className="h-3 w-3" /> Front Page
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500 font-mono">
                    {isHomepage(page) ? <span className="text-blue-600 font-medium">/</span> : <>/{page.slug}</>}
                  </td>
                  <td className="px-6 py-4"><StatusBadge status={page.status} /></td>
                  <td className="px-6 py-4 text-sm text-gray-500">{new Date(page.updated_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => navigate(`/sites/${siteId}/pages/${page.id}/edit`)}
                        className="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50" title="Edit">
                        <Edit className="h-4 w-4" />
                      </button>
                      <button onClick={() => setDeleteTarget(page)}
                        className="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50" title="Delete">
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
