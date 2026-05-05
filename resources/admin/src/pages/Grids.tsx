import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Trash2, Loader2, LayoutGrid, Edit2, Copy } from 'lucide-react';
import { grids } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface GridData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  col_tracks: string;
  areas: string;
  is_preset: boolean;
  positions_count: number;
}

export default function Grids() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<GridData | null>(null);

  const { data, isLoading, error } = useQuery<GridData[]>({
    queryKey: ['grids', siteId],
    queryFn: () => grids.list(siteId).then(r => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (d: Record<string, unknown>) => grids.create(siteId, d),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['grids', siteId] });
      navigate(`/sites/${siteId}/grids/${res.data.data.id}/edit`);
    },
  });

  const seedMutation = useMutation({
    mutationFn: () => grids.seedPresets(siteId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['grids', siteId] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => grids.delete(siteId, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['grids', siteId] });
      setDeleteTarget(null);
    },
  });

  const handleCreate = () => {
    const name = window.prompt('Grid name:');
    if (!name) return;
    createMutation.mutate({
      name,
      col_tracks: '1fr',
      row_tracks: 'auto 1fr auto',
      areas: '"header" "main" "footer"',
    });
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Grid Layouts</h1>
          <p className="mt-1 text-sm text-gray-500">Define page layouts with CSS Grid</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/grids/assignments`)}
            className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
            Assignments
          </button>
          {data && data.length === 0 && (
            <button onClick={() => seedMutation.mutate()} disabled={seedMutation.isPending}
              className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 disabled:opacity-50">
              {seedMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Copy className="h-4 w-4" />}
              Load Presets
            </button>
          )}
          <button onClick={handleCreate} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            <Plus className="h-4 w-4" />
            New Grid
          </button>
        </div>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load grids.</div>}

      {data && data.length === 0 && (
        <EmptyState icon={LayoutGrid} title="No grids yet" description="Create a grid layout or load built-in presets" actionLabel="Load Presets" onAction={() => seedMutation.mutate()} />
      )}

      {data && data.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map((grid) => (
            <div key={grid.id} className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
              <div className="flex items-start justify-between mb-3">
                <div>
                  <h3 className="font-semibold text-gray-900">{grid.name}</h3>
                  {grid.description && <p className="text-xs text-gray-500 mt-0.5">{grid.description}</p>}
                </div>
                <div className="flex gap-1">
                  {grid.is_preset && <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-purple-50 text-purple-700">Preset</span>}
                  {!grid.is_preset && (
                    <button onClick={() => setDeleteTarget(grid)} className="p-1 text-gray-400 hover:text-red-500 rounded"><Trash2 className="h-4 w-4" /></button>
                  )}
                </div>
              </div>

              {/* Mini grid preview */}
              <div className="mb-3 p-2 bg-gray-50 rounded-lg border border-gray-100">
                <div className="text-xs text-gray-400 font-mono mb-1">{grid.col_tracks}</div>
                <div className="flex flex-wrap gap-1">
                  {grid.areas.replace(/"/g, '').split(/\s+/).filter((v: string, i: number, a: string[]) => a.indexOf(v) === i && v !== '').map((area: string) => (
                    <span key={area} className="px-1.5 py-0.5 bg-blue-50 text-blue-700 text-xs rounded">{area}</span>
                  ))}
                </div>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400">{grid.positions_count} positions</span>
                <button onClick={() => navigate(`/sites/${siteId}/grids/${grid.id}/edit`)}
                  className="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 font-medium">
                  <Edit2 className="h-3.5 w-3.5" />
                  Edit
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete grid"
        message={`Delete "${deleteTarget?.name}"? Pages using this grid will fall back to the default layout.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
