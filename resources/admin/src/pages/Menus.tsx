import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Trash2, Loader2, Menu as MenuIcon, Edit2 } from 'lucide-react';
import { menus } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface MenuData {
  id: string;
  name: string;
  slug: string;
  location: string;
  items_count: number;
}

export default function Menus() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<MenuData | null>(null);

  const { data, isLoading, error } = useQuery<MenuData[]>({
    queryKey: ['menus', siteId],
    queryFn: () => menus.list(siteId).then(r => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data: { name: string; location: string }) => menus.create(siteId, data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['menus', siteId] });
      navigate(`/sites/${siteId}/menus/${res.data.data.id}/edit`);
    },
  });

  // Server returns 409 + referring sources when the menu is still in use;
  // a second, explicit confirmation is required to force-delete
  const [forceTarget, setForceTarget] = useState<{ menu: MenuData; count: number; sources: { title: string }[] } | null>(null);

  const deleteMutation = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => menus.delete(siteId, id, force),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['menus', siteId] });
      setDeleteTarget(null);
      setForceTarget(null);
    },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const menu = data?.find(m => m.id === vars.id) ?? deleteTarget;
        if (menu) {
          setForceTarget({
            menu,
            count: e.response.data?.usedOnCount ?? 0,
            sources: e.response.data?.sources ?? [],
          });
        }
        setDeleteTarget(null);
      } else {
        alert(e?.response?.data?.message || 'Failed to delete menu');
      }
    },
  });

  const handleCreate = () => {
    const name = window.prompt('Menu name (e.g. "Main Navigation"):');
    if (name?.trim()) {
      const location = window.prompt('Location (header / footer / sidebar):', 'header') || 'header';
      createMutation.mutate({ name: name.trim(), location });
    }
  };

  const locationLabel = (loc: string) => {
    const labels: Record<string, string> = { header: 'Header', footer: 'Footer', sidebar: 'Sidebar', mobile: 'Mobile' };
    return labels[loc] || loc;
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Menus</h1>
          <p className="mt-1 text-sm text-base-content/50">Manage site navigation</p>
        </div>
        <button onClick={handleCreate} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
          <Plus className="h-4 w-4" />
          New Menu
        </button>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load menus.</div>}

      {data && data.length === 0 && (
        <EmptyState icon={MenuIcon} title="No menus yet" description="Create a menu to add navigation to your site" actionLabel="New Menu" onAction={handleCreate} />
      )}

      {data && data.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map((menu) => (
            <div key={menu.id} className="bg-base-100 rounded-xl border border-base-300 shadow-sm p-5 hover:shadow-md transition-shadow">
              <div className="flex items-start justify-between mb-3">
                <div>
                  <h3 className="font-semibold text-base-content">{menu.name}</h3>
                  <span className="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-50 text-blue-700">{locationLabel(menu.location)}</span>
                </div>
                <button onClick={() => setDeleteTarget(menu)} className="p-1 text-base-content/40 hover:text-red-500 rounded"><Trash2 className="h-4 w-4" /></button>
              </div>
              <p className="text-sm text-base-content/50 mb-4">{menu.items_count} items</p>
              <button
                onClick={() => navigate(`/sites/${siteId}/menus/${menu.id}/edit`)}
                className="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 font-medium"
              >
                <Edit2 className="h-3.5 w-3.5" />
                Edit Menu
              </button>
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete menu"
        message={`Delete "${deleteTarget?.name}"? This will remove all menu items.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id })}
        onClose={() => setDeleteTarget(null)}
      />

      {/* Delete protection: menu is still referenced — explicit force required */}
      <ConfirmDialog
        open={!!forceTarget}
        title="Menu is still in use"
        message={`"${forceTarget?.menu.name}" is used in ${forceTarget?.count} place${forceTarget?.count === 1 ? '' : 's'}: ${forceTarget?.sources.slice(0, 8).map(s => s.title).join(', ')}${(forceTarget?.sources.length ?? 0) > 8 ? '…' : ''}. Deleting it will leave gaps on those pages (they will be flagged for republish). Delete anyway?`}
        confirmText="Force delete"
        variant="danger"
        onConfirm={() => forceTarget && deleteMutation.mutate({ id: forceTarget.menu.id, force: true })}
        onClose={() => setForceTarget(null)}
      />
    </div>
  );
}
