import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Wand2, Trash2 } from 'lucide-react';
import { wizardApi } from './api';
import { STEP_LABELS } from './types';
import type { WizardSession } from './types';

export default function SessionsListPage() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [confirmId, setConfirmId] = useState<string | null>(null);

  const { data: sessions, isLoading } = useQuery({
    queryKey: ['wizard-sessions'],
    queryFn: wizardApi.list,
  });

  const createMut = useMutation({
    mutationFn: (title?: string) => wizardApi.create(title),
    onSuccess: (s) => navigate(`/sites/${siteId}/magazine/wizard/${s.id}`),
  });

  const abandonMut = useMutation({
    mutationFn: (id: string) => wizardApi.abandon(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['wizard-sessions'] }); setConfirmId(null); },
  });

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-lg font-medium text-base-content/90">Magazine Wizard</h1>
          <p className="mt-0.5 text-[15px] text-base-content/40">Art-directed magazine planning, step by step</p>
        </div>
        <button
          onClick={() => createMut.mutate(undefined)}
          disabled={createMut.isPending}
          className="btn btn-primary btn-sm text-[14px] gap-1.5"
        >
          <Plus className="h-3.5 w-3.5" /> New session
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20" />
        </div>
      )}

      {sessions && sessions.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <Wand2 className="h-12 w-12 text-base-content/10 mb-4" strokeWidth={1} />
          <h3 className="text-sm font-medium text-base-content/50 mb-1">No wizard sessions</h3>
          <p className="text-[14px] text-base-content/30 mb-4">Start a new session to plan your magazine with an AI art director</p>
          <button onClick={() => createMut.mutate(undefined)} className="btn btn-primary btn-sm text-[14px]">
            Start first session
          </button>
        </div>
      )}

      {sessions && sessions.length > 0 && (
        <div className="overflow-x-auto">
          <table className="table table-sm">
            <thead>
              <tr className="text-[14px] uppercase tracking-wider text-base-content/30">
                <th>Title</th>
                <th>Step</th>
                <th>Status</th>
                <th>Updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {sessions.map(s => (
                <tr
                  key={s.id}
                  className="cursor-pointer hover:bg-base-200/50"
                  onClick={() => navigate(`/sites/${siteId}/magazine/wizard/${s.id}`)}
                >
                  <td className="text-[15px] font-medium text-base-content/80">
                    {s.title || 'Untitled session'}
                  </td>
                  <td>
                    <span className="badge badge-sm badge-ghost text-[14px]">
                      {STEP_LABELS[s.current_step]} ({s.current_step}/7)
                    </span>
                  </td>
                  <td>
                    <span className={`badge badge-sm text-[11px] ${
                      s.status === 'active' ? 'badge-success' :
                      s.status === 'provisioned' ? 'badge-info' : 'badge-ghost'
                    }`}>
                      {s.status}
                    </span>
                  </td>
                  <td className="text-[15px] text-base-content/40">
                    {new Date(s.updated_at).toLocaleDateString()}
                  </td>
                  <td>
                    <button
                      onClick={(e) => { e.stopPropagation(); setConfirmId(s.id); }}
                      className="btn btn-ghost btn-sm btn-square text-base-content/30 hover:text-error"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Confirm abandon dialog */}
      {confirmId && (
        <div className="modal modal-open">
          <div className="modal-box max-w-sm">
            <h3 className="font-medium text-sm">Abandon session?</h3>
            <p className="text-[14px] text-base-content/50 mt-2">This session will be archived. You can't undo this.</p>
            <div className="modal-action">
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmId(null)}>Cancel</button>
              <button className="btn btn-error btn-sm" onClick={() => abandonMut.mutate(confirmId)}>Abandon</button>
            </div>
          </div>
          <div className="modal-backdrop" onClick={() => setConfirmId(null)} />
        </div>
      )}
    </div>
  );
}
