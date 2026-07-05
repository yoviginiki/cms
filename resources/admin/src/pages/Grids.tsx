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

const THUMB_PALETTE = ['#34d399', '#60a5fa', '#c084fc', '#fbbf24', '#fb923c', '#f472b6', '#2dd4bf', '#a3e635'];

// Parse the CSS grid-template-areas string into rows of cell names
function parseAreas(areas: string): string[][] {
  return (areas.match(/"([^"]+)"/g) || []).map(r => r.replace(/"/g, '').split(/\s+/));
}

function GridThumb({ areas }: { areas: string }) {
  const cells = parseAreas(areas);
  if (!cells.length) return null;
  const names = Array.from(new Set(cells.flat().filter(c => c !== '.')));
  const colorOf = (n: string) => THUMB_PALETTE[names.indexOf(n) % THUMB_PALETTE.length];
  return (
    <div className="grid gap-0.5 rounded-lg overflow-hidden"
      style={{ gridTemplateColumns: `repeat(${cells[0].length}, 1fr)` }}>
      {cells.flatMap((row, ri) => row.map((cell, ci) => (
        <div key={`${ri}-${ci}`} className="h-5 rounded-[3px] flex items-center justify-center overflow-hidden"
          style={cell === '.'
            ? { border: '1px dashed oklch(0.35 0.01 260)' }
            : { backgroundColor: colorOf(cell) + '22', border: `1px solid ${colorOf(cell)}55` }}>
          {cell !== '.' && ci === row.indexOf(cell) && ri === cells.findIndex(r => r.includes(cell)) && (
            <span className="text-[8px] font-medium truncate px-0.5" style={{ color: colorOf(cell) }}>{cell}</span>
          )}
        </div>
      )))}
    </div>
  );
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

  // Create with a default name and jump straight into the editor (name is editable there)
  const handleCreate = () => {
    const n = (data || []).length + 1;
    createMutation.mutate({
      name: `Нов грид ${n}`,
      col_tracks: '1fr',
      row_tracks: 'auto 1fr auto',
      areas: '"header" "main" "footer"',
    });
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Grid Layouts</h1>
          <p className="mt-1 text-sm text-base-content/50">Дефинирай подредбата на страниците с CSS Grid</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/grids/assignments`)}
            className="btn btn-sm btn-ghost border border-base-300 font-medium">
            Assignments
          </button>
          {data && data.length === 0 && (
            <button onClick={() => seedMutation.mutate()} disabled={seedMutation.isPending}
              className="btn btn-sm btn-ghost border border-base-300 font-medium gap-2 disabled:opacity-50">
              {seedMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Copy className="h-4 w-4" />}
              Load Presets
            </button>
          )}
          <button onClick={handleCreate} disabled={createMutation.isPending} className="btn btn-sm btn-primary gap-2">
            {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
            New Grid
          </button>
        </div>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/30" /></div>}
      {error && <div className="rounded-lg bg-error/10 border border-error/30 p-4 text-sm text-error">Failed to load grids.</div>}

      {data && data.length === 0 && (
        <EmptyState icon={LayoutGrid} title="No grids yet" description="Create a grid layout or load built-in presets" actionLabel="Load Presets" onAction={() => seedMutation.mutate()} />
      )}

      {data && data.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map((grid) => (
            <div key={grid.id}
              className="group bg-base-100 rounded-xl border border-base-300 p-5 hover:border-primary/50 transition-colors cursor-pointer"
              onClick={() => navigate(`/sites/${siteId}/grids/${grid.id}/edit`)}>
              <div className="flex items-start justify-between mb-3">
                <div className="min-w-0">
                  <h3 className="font-semibold text-base-content truncate">{grid.name}</h3>
                  {grid.description && <p className="text-xs text-base-content/50 mt-0.5 line-clamp-2">{grid.description}</p>}
                </div>
                <div className="flex gap-1 shrink-0">
                  {grid.is_preset && <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-accent/15 text-accent">Preset</span>}
                  {!grid.is_preset && (
                    <button onClick={e => { e.stopPropagation(); setDeleteTarget(grid); }}
                      className="p-1 text-base-content/30 hover:text-error rounded opacity-0 group-hover:opacity-100 transition-opacity"><Trash2 className="h-4 w-4" /></button>
                  )}
                </div>
              </div>

              {/* Layout preview */}
              <div className="mb-3 p-2.5 bg-base-200/50 rounded-lg border border-base-300">
                <GridThumb areas={grid.areas} />
                <div className="text-[10px] text-base-content/35 font-mono mt-2 truncate">{grid.col_tracks}</div>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-xs text-base-content/40">{grid.positions_count} positions</span>
                <span className="inline-flex items-center gap-1 text-sm text-primary font-medium">
                  <Edit2 className="h-3.5 w-3.5" />
                  Edit
                </span>
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
