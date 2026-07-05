import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Sparkles, Trash2 } from 'lucide-react';
import { studioApi } from './api';
import { STATUS_LABELS } from './types';

export default function IssueStudioListPage() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [confirmId, setConfirmId] = useState<string | null>(null);

  const { data: sessions, isLoading } = useQuery({
    queryKey: ['issue-studio-sessions', siteId],
    queryFn: () => studioApi.list(siteId),
  });

  const createMut = useMutation({
    mutationFn: () => studioApi.create(siteId),
    onSuccess: (s) => navigate(`/sites/${siteId}/issue-studio/${s.id}`),
  });

  const abandonMut = useMutation({
    mutationFn: (id: string) => studioApi.abandon(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['issue-studio-sessions', siteId] });
      setConfirmId(null);
    },
  });

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-lg font-medium text-base-content/90">Issue Studio</h1>
          <p className="mt-0.5 text-[15px] text-base-content/40">
            Tell the editorial director what you want — it builds the magazine
          </p>
        </div>
        <button
          onClick={() => createMut.mutate()}
          disabled={createMut.isPending}
          className="btn btn-primary btn-sm text-[14px] gap-1.5"
        >
          <Plus className="h-3.5 w-3.5" /> New issue
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20" />
        </div>
      )}

      {sessions && sessions.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <Sparkles className="h-12 w-12 text-base-content/10 mb-4" strokeWidth={1} />
          <h3 className="text-sm font-medium text-base-content/50 mb-1">No issues yet</h3>
          <p className="text-[14px] text-base-content/30 mb-4 max-w-sm">
            Start a chat, answer a couple of easy questions, drop in your texts and photos —
            the wizard does the rest.
          </p>
          <button onClick={() => createMut.mutate()} className="btn btn-primary btn-sm text-[14px]">
            Start your first issue
          </button>
        </div>
      )}

      {sessions && sessions.length > 0 && (
        <div className="border border-base-300 divide-y divide-base-300">
          {sessions.map((s) => (
            <div
              key={s.id}
              className="flex items-center gap-4 px-4 py-3 hover:bg-base-200/40 cursor-pointer"
              onClick={() => navigate(`/sites/${siteId}/issue-studio/${s.id}`)}
            >
              <Sparkles className="h-4 w-4 text-base-content/25 shrink-0" />
              <div className="flex-1 min-w-0">
                <div className="text-[14px] text-base-content/80 truncate">
                  {s.title || s.topic || 'Untitled issue'}
                </div>
                <div className="text-[12px] text-base-content/35">
                  {s.material_count} material{s.material_count === 1 ? '' : 's'} ·{' '}
                  {new Date(s.updated_at).toLocaleString()}
                </div>
              </div>
              <span
                className={`text-[11px] uppercase tracking-wide px-2 py-0.5 border ${
                  s.status === 'complete'
                    ? 'border-success/40 text-success'
                    : s.status === 'abandoned'
                      ? 'border-base-300 text-base-content/30'
                      : 'border-primary/40 text-primary'
                }`}
              >
                {STATUS_LABELS[s.status]}
              </span>
              {confirmId === s.id ? (
                <button
                  onClick={(e) => { e.stopPropagation(); abandonMut.mutate(s.id); }}
                  className="btn btn-error btn-xs text-[12px]"
                >
                  Confirm
                </button>
              ) : (
                <button
                  onClick={(e) => { e.stopPropagation(); setConfirmId(s.id); }}
                  className="btn btn-ghost btn-xs text-base-content/30 hover:text-error"
                  title="Abandon session"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
