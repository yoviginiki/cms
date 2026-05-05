import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import {
  Loader2, CheckCircle, FileOutput, Clock, AlertTriangle,
} from 'lucide-react';
import { issueComposer } from '@/lib/api';
import IssueComposerLayout from './IssueComposerLayout';

interface RunEntry {
  id: string;
  phase: string;
  claude_model: string;
  claude_input_tokens: number;
  claude_output_tokens: number;
  prompt_version: string;
  created_at: string;
}

export default function Step7HandoffScreen() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);

  const { data: issue, isLoading } = useQuery({
    queryKey: ['issue', siteId, issueId],
    queryFn: () => issueComposer.get(siteId, issueId).then((r: any) => r.data.data),
  });

  const layout: any[] = (issue as any)?.layout_final || [];
  const sections = new Set(layout.map(p => p.section_id));
  const totalElements = layout.reduce((sum: number, p: any) => {
    return sum + Object.keys(p.slots || {}).length;
  }, 0);

  const latestRuns: Record<string, RunEntry> = (issue as any)?.latest_runs || {};
  const allRuns = Object.values(latestRuns).sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
  );

  const isHandedOff = (issue as any)?.status === 'handed_off';
  const linkedPageId = (issue as any)?.linked_page_id;

  // Handoff mutation
  const handoffMutation = useMutation({
    mutationFn: () => issueComposer.handoff(siteId, issueId).then((r: any) => r.data),
    onSuccess: (data) => {
      const pageId = data?.data?.page_id;
      if (pageId) {
        navigate(`/sites/${siteId}/pages/${pageId}/edit`);
      }
    },
    onError: (err: any) => {
      setError(err?.response?.data?.message || 'Handoff failed');
    },
  });

  return (
    <IssueComposerLayout issueId={issueId} issueStatus={(issue as any)?.status} currentStep="handoff">
      <div className="max-w-[700px] mx-auto px-6 py-12">
        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-32">
            <Loader2 size={24} className="animate-spin text-base-content/30" />
          </div>
        )}

        {!isLoading && (
          <>
            {/* Header */}
            <div className="text-center mb-10">
              <FileOutput size={40} className="mx-auto mb-4 text-primary/50" />
              <h1 className="text-2xl font-semibold text-base-content mb-2">Handoff to Magazine Editor</h1>
              <p className="text-sm text-base-content/50 max-w-md mx-auto">
                This will create a new page in the Magazine Editor with all your layout pages and elements.
              </p>
            </div>

            {/* Summary card */}
            <div className="bg-base-200/30 border border-base-300/20 rounded-lg p-6 mb-8">
              <h3 className="text-xs font-medium text-base-content/40 uppercase mb-4">Summary</h3>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                  <div className="text-2xl font-semibold text-base-content">{layout.length}</div>
                  <div className="text-xs text-base-content/40">Pages</div>
                </div>
                <div>
                  <div className="text-2xl font-semibold text-base-content">{sections.size}</div>
                  <div className="text-xs text-base-content/40">Sections</div>
                </div>
                <div>
                  <div className="text-2xl font-semibold text-base-content">{totalElements}</div>
                  <div className="text-xs text-base-content/40">Elements</div>
                </div>
              </div>
            </div>

            {/* Already handed off */}
            {isHandedOff && linkedPageId && (
              <div className="bg-success/5 border border-success/20 rounded-lg p-4 mb-6 flex items-center gap-3">
                <CheckCircle size={18} className="text-success shrink-0" />
                <div className="flex-1">
                  <p className="text-sm text-base-content/70">Already handed off.</p>
                  <button
                    onClick={() => navigate(`/sites/${siteId}/pages/${linkedPageId}/edit`)}
                    className="text-xs text-primary hover:underline mt-1"
                  >
                    Open in Magazine Editor
                  </button>
                </div>
              </div>
            )}

            {/* Error */}
            {error && (
              <div className="bg-error/5 border border-error/20 rounded-lg p-4 mb-6 flex items-center gap-3">
                <AlertTriangle size={18} className="text-error shrink-0" />
                <p className="text-sm text-base-content/70">{error}</p>
              </div>
            )}

            {/* CTA */}
            <button
              onClick={() => { setError(null); handoffMutation.mutate(); }}
              disabled={handoffMutation.isPending || layout.length === 0}
              className="btn btn-primary w-full gap-2"
            >
              {handoffMutation.isPending ? (
                <><Loader2 size={16} className="animate-spin" /> Creating page...</>
              ) : isHandedOff ? (
                'Re-create & open in Magazine Editor'
              ) : (
                'Create & open in Magazine Editor'
              )}
            </button>

            {layout.length === 0 && (
              <p className="text-xs text-base-content/30 text-center mt-3">
                Go back to the Layout step to generate a layout first.
              </p>
            )}

            {/* Run history */}
            {allRuns.length > 0 && (
              <div className="mt-12">
                <h3 className="text-xs font-medium text-base-content/40 uppercase mb-4">Run history</h3>
                <div className="space-y-2">
                  {allRuns.map((run) => (
                    <div
                      key={run.id}
                      className="flex items-center gap-3 px-4 py-3 border border-base-300/15 rounded-md"
                    >
                      <Clock size={14} className="text-base-content/25 shrink-0" />
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-medium text-base-content/60 capitalize">
                          {run.phase}
                        </div>
                        <div className="text-[10px] text-base-content/30">
                          {run.claude_model} &middot;{' '}
                          {run.claude_input_tokens + run.claude_output_tokens} tokens &middot;{' '}
                          {run.prompt_version}
                        </div>
                      </div>
                      <div className="text-[10px] text-base-content/25 shrink-0">
                        {new Date(run.created_at).toLocaleString()}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </IssueComposerLayout>
  );
}
