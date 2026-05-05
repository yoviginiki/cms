import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Loader2, Sparkles, LayoutGrid, X, ArrowRight, RefreshCw,
} from 'lucide-react';
import { issueComposer } from '@/lib/api';
import IssueComposerLayout from './IssueComposerLayout';

interface PageSpec {
  page_number: number;
  section_id: string;
  template_id: string;
  density: string;
  slots: Record<string, unknown>;
}

const DENSITY_COLORS: Record<string, { bg: string; dot: string; label: string }> = {
  text_heavy: { bg: 'bg-blue-500/10', dot: 'bg-blue-500', label: 'Text-heavy' },
  visual: { bg: 'bg-green-500/10', dot: 'bg-green-500', label: 'Visual' },
  break: { bg: 'bg-yellow-500/10', dot: 'bg-yellow-500', label: 'Break' },
  reflection: { bg: 'bg-purple-500/10', dot: 'bg-purple-500', label: 'Reflection' },
};

function densityInfo(d: string) {
  return DENSITY_COLORS[d] || { bg: 'bg-base-300/10', dot: 'bg-base-content/30', label: d };
}

function templateLabel(id: string): string {
  return id.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

export default function Step5LayoutScreen() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [isRunning, setIsRunning] = useState(false);
  const [drawerPage, setDrawerPage] = useState<PageSpec | null>(null);
  const [editedSlots, setEditedSlots] = useState<Record<string, unknown> | null>(null);

  const { data: issue, isLoading } = useQuery({
    queryKey: ['issue', siteId, issueId],
    queryFn: () => issueComposer.get(siteId, issueId).then((r: any) => r.data.data),
    refetchInterval: isRunning ? 3000 : false,
  });

  const layout: PageSpec[] = (issue as any)?.layout_final || [];
  const latestRun = (issue as any)?.latest_runs?.layout;
  const hasLayout = layout.length > 0;

  // Run layout generation
  const runMutation = useMutation({
    mutationFn: async () => {
      setIsRunning(true);
      return issueComposer.runLayout(siteId, issueId).then((r: any) => r.data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] });
      setIsRunning(false);
    },
    onError: () => setIsRunning(false),
  });

  // Gate: all pages have template_id
  const allValid = layout.length > 0 && layout.every(p => p.template_id);

  // Advance to handoff
  const advanceMutation = useMutation({
    mutationFn: () => issueComposer.update(siteId, issueId, { status: 'ready' }),
    onSuccess: () => navigate(`/sites/${siteId}/issue-composer/${issueId}/handoff`),
  });

  // Open drawer
  const openDrawer = (page: PageSpec) => {
    setDrawerPage(page);
    setEditedSlots({ ...page.slots });
  };

  // Save slot edits back to layout_final
  const saveSlots = useMutation({
    mutationFn: async () => {
      if (!drawerPage || !editedSlots) return;
      const updatedLayout = layout.map(p =>
        p.page_number === drawerPage.page_number ? { ...p, slots: editedSlots } : p,
      );
      return issueComposer.update(siteId, issueId, { layout_final: updatedLayout } as any);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] });
      setDrawerPage(null);
      setEditedSlots(null);
    },
  });

  return (
    <IssueComposerLayout issueId={issueId} issueStatus={(issue as any)?.status} currentStep="layout">
      <div className="max-w-[1200px] mx-auto px-6 py-8">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-semibold text-base-content">Layout draft</h1>
            <p className="text-sm text-base-content/50 mt-1">
              {hasLayout
                ? `${layout.length} pages generated`
                : 'Generate a layout from your curated content'}
            </p>
          </div>

          <button
            onClick={() => runMutation.mutate()}
            disabled={isRunning}
            className="btn btn-primary btn-sm gap-2"
          >
            {isRunning ? (
              <><Loader2 size={14} className="animate-spin" /> Generating...</>
            ) : hasLayout ? (
              <><RefreshCw size={14} /> Regenerate</>
            ) : (
              <><Sparkles size={14} /> Generate layout</>
            )}
          </button>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-32">
            <Loader2 size={24} className="animate-spin text-base-content/30" />
          </div>
        )}

        {/* Empty state */}
        {!isLoading && !hasLayout && !isRunning && (
          <div className="flex flex-col items-center justify-center py-32 text-center">
            <LayoutGrid size={48} className="text-base-content/15 mb-4" />
            <h2 className="text-lg font-medium text-base-content/50 mb-2">No layout yet</h2>
            <p className="text-sm text-base-content/35 mb-6 max-w-md">
              Click "Generate layout" to have AI assign templates and fill slots based on your curated content.
            </p>
            <button
              onClick={() => runMutation.mutate()}
              className="btn btn-primary btn-sm gap-2"
            >
              <Sparkles size={14} /> Generate layout
            </button>
          </div>
        )}

        {/* Page grid */}
        {hasLayout && (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {layout.map((page) => {
              const di = densityInfo(page.density);
              const slotTitle = typeof page.slots?.title === 'string' ? page.slots.title : '';

              return (
                <button
                  key={page.page_number}
                  onClick={() => openDrawer(page)}
                  className="group border border-base-300/30 rounded-lg p-3 text-left hover:border-primary/40 hover:bg-primary/3 transition-all"
                >
                  {/* Page number */}
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-[10px] font-medium text-base-content/30">
                      Page {page.page_number}
                    </span>
                    <span className={`w-2 h-2 rounded-full ${di.dot}`} title={di.label} />
                  </div>

                  {/* Template */}
                  <div className="text-xs font-medium text-base-content/70 mb-1 truncate">
                    {templateLabel(page.template_id)}
                  </div>

                  {/* Section */}
                  <div className="text-[10px] text-base-content/35 mb-2 truncate">
                    {page.section_id}
                  </div>

                  {/* Slot preview */}
                  {slotTitle && (
                    <div className="text-[10px] text-base-content/25 truncate italic">
                      {slotTitle}
                    </div>
                  )}
                </button>
              );
            })}
          </div>
        )}

        {/* Bottom bar */}
        {hasLayout && (
          <div className="flex items-center justify-between mt-8 pt-6 border-t border-base-300/20">
            <span className="text-sm text-base-content/40">
              {layout.length} pages total
              {latestRun && (
                <> &middot; {latestRun.claude_input_tokens + latestRun.claude_output_tokens} tokens used</>
              )}
            </span>

            <button
              onClick={() => advanceMutation.mutate()}
              disabled={!allValid || advanceMutation.isPending}
              className="btn btn-primary btn-sm gap-2"
            >
              {advanceMutation.isPending ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <>Continue to handoff <ArrowRight size={14} /></>
              )}
            </button>
          </div>
        )}
      </div>

      {/* Drawer */}
      {drawerPage && (
        <div className="fixed inset-0 z-50 flex">
          <div className="flex-1 bg-black/30" onClick={() => { setDrawerPage(null); setEditedSlots(null); }} />
          <div className="w-[420px] bg-base-100 border-l border-base-300/30 overflow-y-auto">
            <div className="flex items-center justify-between px-5 py-4 border-b border-base-300/20">
              <h3 className="text-sm font-semibold text-base-content">
                Page {drawerPage.page_number} &mdash; {templateLabel(drawerPage.template_id)}
              </h3>
              <button onClick={() => { setDrawerPage(null); setEditedSlots(null); }} className="text-base-content/30 hover:text-base-content/60">
                <X size={16} />
              </button>
            </div>

            <div className="p-5 space-y-4">
              {/* Meta */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[10px] text-base-content/30 uppercase">Section</label>
                  <div className="text-sm text-base-content/70">{drawerPage.section_id}</div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/30 uppercase">Density</label>
                  <div className="flex items-center gap-1.5">
                    <span className={`w-2 h-2 rounded-full ${densityInfo(drawerPage.density).dot}`} />
                    <span className="text-sm text-base-content/70">{densityInfo(drawerPage.density).label}</span>
                  </div>
                </div>
              </div>

              {/* Slots */}
              <div>
                <h4 className="text-xs font-medium text-base-content/50 mb-3">Slots</h4>
                {editedSlots && Object.entries(editedSlots).map(([key, value]) => (
                  <div key={key} className="mb-3">
                    <label className="text-[10px] text-base-content/30 uppercase block mb-1">{key}</label>
                    {typeof value === 'string' && value.length > 120 ? (
                      <textarea
                        value={value}
                        onChange={(e) => setEditedSlots({ ...editedSlots, [key]: e.target.value })}
                        className="textarea textarea-bordered textarea-sm w-full h-32 text-xs"
                      />
                    ) : typeof value === 'string' ? (
                      <input
                        type="text"
                        value={value}
                        onChange={(e) => setEditedSlots({ ...editedSlots, [key]: e.target.value })}
                        className="input input-bordered input-sm w-full text-xs"
                      />
                    ) : (
                      <pre className="text-[10px] bg-base-200/50 p-2 rounded overflow-auto max-h-40">
                        {JSON.stringify(value, null, 2)}
                      </pre>
                    )}
                  </div>
                ))}
              </div>

              {/* Save */}
              <button
                onClick={() => saveSlots.mutate()}
                disabled={saveSlots.isPending}
                className="btn btn-primary btn-sm w-full"
              >
                {saveSlots.isPending ? <Loader2 size={14} className="animate-spin" /> : 'Save changes'}
              </button>
            </div>
          </div>
        </div>
      )}
    </IssueComposerLayout>
  );
}
