import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { Plus, Trash2, Loader2, Database, Pencil, Table2 } from 'lucide-react';
import { collections, type Collection } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useToast } from '@/components/ui/Toast';
import { Modal, TierPicker, TierBadge, apiErr, validationErrors } from './shared';

function timeAgo(iso?: string): string {
  if (!iso) return '—';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60_000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
}

interface ForceDeleteInfo {
  collection: Collection;
  recordCount: number;
  relationDependents: string[];
  usedOnCount: number;
  sources: { title: string }[];
}

export default function CollectionsList() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const [createOpen, setCreateOpen] = useState(false);
  const [name, setName] = useState('');
  const [icon, setIcon] = useState('');
  const [tier, setTier] = useState<Collection['tier']>('static');
  const [createErrors, setCreateErrors] = useState<Record<string, string>>({});

  const [deleteTarget, setDeleteTarget] = useState<Collection | null>(null);
  const [forceTarget, setForceTarget] = useState<ForceDeleteInfo | null>(null);

  const { data, isLoading, error } = useQuery<Collection[]>({
    queryKey: ['collections', siteId],
    queryFn: () => collections.list(siteId).then((r) => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      collections.create(siteId, {
        name: name.trim(),
        icon: icon.trim() || undefined,
        tier,
        schema: { fields: [], title_field: '' },
      }),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      navigate(`/sites/${siteId}/collections/${res.data.data.id}/schema`);
    },
    onError: (e: any) => {
      const errs = validationErrors(e);
      if (Object.keys(errs).length > 0) setCreateErrors(errs);
      else toast({ type: 'error', message: apiErr(e) });
    },
  });

  // Server answers 409 + usage details when the collection still has records
  // or other collections point relations at it; an explicit force is required.
  const deleteMutation = useMutation({
    mutationFn: ({ id, force }: { id: string; force?: boolean }) => collections.delete(siteId, id, force),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      setDeleteTarget(null);
      setForceTarget(null);
      toast({ type: 'success', message: 'Collection deleted.' });
    },
    onError: (e: any, vars) => {
      if (e?.response?.status === 409) {
        const collection = data?.find((c) => c.id === vars.id) ?? deleteTarget;
        if (collection) {
          setForceTarget({
            collection,
            recordCount: e.response.data?.recordCount ?? 0,
            relationDependents: e.response.data?.relationDependents ?? [],
            usedOnCount: e.response.data?.usedOnCount ?? 0,
            sources: e.response.data?.sources ?? [],
          });
        }
        setDeleteTarget(null);
      } else {
        toast({ type: 'error', message: apiErr(e) });
        setDeleteTarget(null);
      }
    },
  });

  const openCreate = () => {
    setName('');
    setIcon('');
    setTier('static');
    setCreateErrors({});
    setCreateOpen(true);
  };

  const submitCreate = () => {
    if (!name.trim()) {
      setCreateErrors({ name: 'Name is required' });
      return;
    }
    setCreateErrors({});
    createMutation.mutate();
  };

  const forceMessage = (f: ForceDeleteInfo) => {
    const parts: string[] = [];
    if (f.recordCount > 0) parts.push(`${f.recordCount} record${f.recordCount === 1 ? '' : 's'} will be deleted`);
    if (f.relationDependents.length > 0) parts.push(`relation fields in ${f.relationDependents.join(', ')} point at it`);
    if (f.usedOnCount > 0) {
      const names = f.sources.slice(0, 6).map((s) => s.title).join(', ');
      parts.push(`it is used on ${f.usedOnCount} page${f.usedOnCount === 1 ? '' : 's'}${names ? ` (${names}${f.sources.length > 6 ? '…' : ''})` : ''}`);
    }
    return `"${f.collection.name}" is still in use: ${parts.join('; ')}. Deleting it cannot be undone. Delete anyway?`;
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-base-content">Collections</h1>
          <p className="mt-1 text-sm text-base-content/50">Structured content types with their own fields and records</p>
        </div>
        <button onClick={openCreate} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          <Plus size={14} />
          New Collection
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-base-content/40" />
        </div>
      )}
      {!!error && (
        <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load collections.</div>
      )}

      {data && data.length === 0 && (
        <EmptyState
          icon={Database}
          title="No collections yet"
          description="Define a content type — products, team members, events — and start adding records"
          actionLabel="New Collection"
          onAction={openCreate}
        />
      )}

      {data && data.length > 0 && (
        <div className="overflow-x-auto rounded-box border border-base-300/40 bg-base-100">
          <table className="table table-sm">
            <thead>
              <tr className="border-b border-base-300/40">
                <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Collection</th>
                <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Tier</th>
                <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Fields</th>
                <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Records</th>
                <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Updated</th>
                <th className="w-32" />
              </tr>
            </thead>
            <tbody>
              {data.map((c) => (
                <tr key={c.id} className="border-b border-base-300/20 hover:bg-base-300/10 transition-colors">
                  <td>
                    <Link to={`/sites/${siteId}/collections/${c.id}/records`} className="flex items-center gap-2.5 group">
                      <span className="w-8 h-8 flex items-center justify-center bg-base-300/30 rounded-box text-[15px] shrink-0">
                        {c.icon || <Database size={15} className="text-base-content/30" />}
                      </span>
                      <div className="min-w-0">
                        <div className="text-[13px] font-medium text-base-content group-hover:text-primary transition-colors truncate">{c.name}</div>
                        <div className="text-[11px] text-base-content/35 font-mono truncate">{c.slug}</div>
                      </div>
                    </Link>
                  </td>
                  <td><TierBadge tier={c.tier} /></td>
                  <td className="text-[13px] text-base-content/60">{c.schema?.fields?.length ?? 0}</td>
                  <td className="text-[13px] text-base-content/60">{c.records_count}</td>
                  <td className="text-[12px] text-base-content/40">{timeAgo(c.updated_at)}</td>
                  <td>
                    <div className="flex items-center justify-end gap-0.5">
                      <Link to={`/sites/${siteId}/collections/${c.id}/records`}
                        className="btn btn-ghost btn-xs btn-square" title="Records">
                        <Table2 size={13} />
                      </Link>
                      <Link to={`/sites/${siteId}/collections/${c.id}/schema`}
                        className="btn btn-ghost btn-xs btn-square" title="Edit schema">
                        <Pencil size={13} />
                      </Link>
                      <button
                        onClick={() => setDeleteTarget(c)}
                        disabled={c.is_system}
                        className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error disabled:opacity-30"
                        title={c.is_system ? 'System collections can’t be deleted' : 'Delete'}
                      >
                        <Trash2 size={13} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create dialog */}
      <Modal open={createOpen} onClose={() => setCreateOpen(false)} title="New collection">
        <div className="space-y-4" onKeyDown={(e) => { if (e.key === 'Enter' && (e.target as HTMLElement).tagName === 'INPUT') submitCreate(); }}>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Name</label>
            <input
              autoFocus
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Products, Team members, Events"
              className={`input input-bordered input-sm w-full text-[13px] ${createErrors.name ? 'input-error' : ''}`}
            />
            {createErrors.name && <p className="text-[11px] text-error mt-1">{createErrors.name}</p>}
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Icon <span className="text-base-content/30">(optional — an emoji or short text)</span></label>
            <input
              value={icon}
              onChange={(e) => setIcon(e.target.value)}
              placeholder="e.g. 📦"
              maxLength={8}
              className="input input-bordered input-sm w-24 text-[13px]"
            />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Tier</label>
            <TierPicker value={tier} onChange={setTier} />
            {createErrors.tier && <p className="text-[11px] text-error mt-1">{createErrors.tier}</p>}
          </div>
          {Object.entries(createErrors).filter(([k]) => k !== 'name' && k !== 'tier').length > 0 && (
            <div className="border border-error/30 bg-error/10 rounded-box px-3 py-2 text-[12px] text-error space-y-0.5">
              {Object.entries(createErrors).filter(([k]) => k !== 'name' && k !== 'tier').map(([k, msg]) => <p key={k}>{msg}</p>)}
            </div>
          )}
          <div className="flex justify-end gap-2 pt-2">
            <button onClick={() => setCreateOpen(false)} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
            <button onClick={submitCreate} disabled={createMutation.isPending || !name.trim()} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
              {createMutation.isPending && <Loader2 size={13} className="animate-spin" />}
              Create & define fields
            </button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete collection"
        message={`Delete "${deleteTarget?.name}"? Its schema and all its records will be removed.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate({ id: deleteTarget.id })}
        onClose={() => setDeleteTarget(null)}
      />

      <ConfirmDialog
        open={!!forceTarget}
        title="Collection is still in use"
        message={forceTarget ? forceMessage(forceTarget) : ''}
        confirmText="Force delete"
        variant="danger"
        onConfirm={() => forceTarget && deleteMutation.mutate({ id: forceTarget.collection.id, force: true })}
        onClose={() => setForceTarget(null)}
      />
    </div>
  );
}
