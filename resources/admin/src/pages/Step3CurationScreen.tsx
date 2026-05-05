import { useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Loader2, Sparkles, CheckCircle, XCircle, Scissors, RotateCcw,
  Lock, Unlock, Plus, ArrowRight,
} from 'lucide-react';
import { issueComposer } from '@/lib/api';
import IssueComposerLayout from './IssueComposerLayout';

interface Decision { item_id: string; decision: string; reason: string; trim_note?: string; }
interface Section { id: string; title: string; one_line_description: string; emotional_register: string; item_ids: string[]; locked?: boolean; }
interface FlowEntry { section_id: string; density: string; position: number; }
interface Gap { description: string; suggested_fix?: string; }
interface CurationState {
  decisions: Decision[];
  sections: Section[];
  flow: FlowEntry[];
  gaps: Gap[];
}

export default function Step3CurationScreen() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [directive, setDirective] = useState('');
  const [showDirective, setShowDirective] = useState(false);
  const [isRunning, setIsRunning] = useState(false);
  const [editedState, setEditedState] = useState<CurationState | null>(null);
  const [previewItemId, setPreviewItemId] = useState<string | null>(null);

  const { data: issue, isLoading } = useQuery({
    queryKey: ['issue', siteId, issueId],
    queryFn: () => issueComposer.get(siteId, issueId).then((r: any) => r.data.data),
    refetchInterval: isRunning ? 3000 : false,
  });

  const latestRun = (issue as any)?.latest_runs?.curation;
  const aiOutput: CurationState | null = latestRun?.output_jsonb || null;
  const state = editedState || aiOutput;
  const items = (issue as any)?.content_items || [];

  // Initialize edited state from AI output
  const initEdited = useCallback((output: CurationState) => {
    setEditedState(JSON.parse(JSON.stringify(output)));
  }, []);

  // When AI output arrives and no edits yet
  if (aiOutput && !editedState) {
    initEdited(aiOutput);
  }

  // Get item info by id
  const getItem = (id: string) => items.find((i: any) => i.id === id);
  const getItemTitle = (id: string) => {
    const item = getItem(id);
    if (!item) return id.slice(0, 8) + '...';
    if (item.source_type === 'post') return item.post_title || item.source_id?.slice(0, 8) || 'Post';
    if (item.source_type === 'extra_text') return (item.extra_payload as any)?.caption || 'Extra text';
    if (item.source_type === 'extra_image') return 'Image';
    if (item.source_type === 'extra_video') return 'Video';
    return item.source_type;
  };

  // Run curation
  const runMutation = useMutation({
    mutationFn: async () => {
      setIsRunning(true);
      const lockedIds = (editedState?.sections || []).filter(s => s.locked).map(s => s.id);
      // Call the curation endpoint
      const resp = await fetch(`/api/v1/sites/${siteId}/magazine-issues/${issueId}/curation/run`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '') },
        credentials: 'include',
        body: JSON.stringify({ directive: directive || null, locked_section_ids: lockedIds }),
      });
      return resp.json();
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] });
      if (data?.data?.output_jsonb) {
        initEdited(data.data.output_jsonb);
      }
      setIsRunning(false);
      setShowDirective(false);
    },
    onError: () => setIsRunning(false),
  });

  // Override a decision
  const overrideDecision = (itemId: string, newDecision: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      decisions: editedState.decisions.map(d =>
        d.item_id === itemId ? { ...d, decision: newDecision, reason: 'Editor override' } : d
      ),
    });
  };

  // Edit section title
  const editSectionTitle = (secId: string, title: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.map(s => s.id === secId ? { ...s, title } : s),
    });
  };

  // Toggle section lock
  const toggleLock = (secId: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.map(s => s.id === secId ? { ...s, locked: !s.locked } : s),
    });
  };

  // Add section
  const addSection = () => {
    if (!editedState) return;
    const newId = `sec_${editedState.sections.length + 1}`;
    setEditedState({
      ...editedState,
      sections: [...editedState.sections, { id: newId, title: 'New section', one_line_description: '', emotional_register: 'informative', item_ids: [] }],
      flow: [...editedState.flow, { section_id: newId, density: 'text_heavy', position: editedState.flow.length + 1 }],
    });
  };

  // Delete section
  const deleteSection = (secId: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.filter(s => s.id !== secId),
      flow: editedState.flow.filter(f => f.section_id !== secId),
    });
  };

  // Move item from one section to another
  const moveItemToSection = (itemId: string, fromSecId: string, toSecId: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.map(s => {
        if (s.id === fromSecId) return { ...s, item_ids: s.item_ids.filter(id => id !== itemId) };
        if (s.id === toSecId) return { ...s, item_ids: [...s.item_ids, itemId] };
        return s;
      }),
    });
  };

  // Remove item from section (back to unassigned)
  const removeItemFromSection = (itemId: string, secId: string) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.map(s =>
        s.id === secId ? { ...s, item_ids: s.item_ids.filter(id => id !== itemId) } : s
      ),
    });
  };

  // Move item up/down within a section
  const moveItemInSection = (secId: string, itemId: string, direction: -1 | 1) => {
    if (!editedState) return;
    setEditedState({
      ...editedState,
      sections: editedState.sections.map(s => {
        if (s.id !== secId) return s;
        const idx = s.item_ids.indexOf(itemId);
        if (idx < 0) return s;
        const newIdx = idx + direction;
        if (newIdx < 0 || newIdx >= s.item_ids.length) return s;
        const arr = [...s.item_ids];
        [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
        return { ...s, item_ids: arr };
      }),
    });
  };

  // Get unassigned kept items (not in any section)
  const getUnassignedItems = (): string[] => {
    if (!state) return [];
    const assignedIds = new Set(state.sections.flatMap(s => s.item_ids));
    return (state.decisions || [])
      .filter(d => d.decision === 'kept' && !assignedIds.has(d.item_id))
      .map(d => d.item_id);
  };

  // Advance to layout
  const advanceMutation = useMutation({
    mutationFn: () => issueComposer.update(siteId, issueId, {
      status: 'laid_out',
      curation_final: editedState,
    }),
    onSuccess: () => navigate(`/sites/${siteId}/issue-composer/${issueId}/layout`),
  });

  // Gate checks
  const keptCount = (state?.decisions || []).filter(d => d.decision === 'kept').length;
  const sectionCount = (state?.sections || []).length;
  const canAdvance = sectionCount >= 3 && keptCount >= 5;
  const estPages = Math.max(sectionCount * 3, keptCount * 2);

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  if (isLoading) {
    return (
      <IssueComposerLayout currentStep="curation" issueId={issueId} issueStatus={(issue as any)?.status}>
        <div className="flex items-center justify-center h-full"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>
      </IssueComposerLayout>
    );
  }

  return (
    <IssueComposerLayout currentStep="curation" issueId={issueId} issueStatus={(issue as any)?.status}>
      <div className="flex flex-col h-full" data-theme={adminTheme}>
        {/* Top bar */}
        <div className="px-4 py-3 border-b border-base-300/20 shrink-0">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-base font-medium text-base-content/90">Curation & narrative flow</h1>
              <p className="text-[11px] text-base-content/40">{items.length} items · {keptCount} kept · {sectionCount} sections · ~{estPages} pages</p>
            </div>
          <div className="flex items-center gap-2">
            {state && (
              <button onClick={() => setShowDirective(!showDirective)}
                className="btn btn-ghost btn-sm text-[12px] gap-1">
                <RotateCcw size={12} /> Regenerate
              </button>
            )}
            <button onClick={() => runMutation.mutate()} disabled={isRunning}
              className="btn btn-primary btn-sm text-[12px] gap-1">
              {isRunning ? <Loader2 size={12} className="animate-spin" /> : <Sparkles size={12} />}
              {state ? 'Re-run AI' : 'Generate proposal'}
            </button>
          </div>
          </div>
          {/* Step explanation */}
          <div className="px-4 pb-2 text-[11px] text-base-content/40 leading-relaxed max-w-3xl">
            <strong className="text-base-content/60">What is this step?</strong> The AI analyzed your posts and organized them into <strong>sections</strong> (like chapters).
            Each section groups related content together. The <strong>left column</strong> shows which posts are kept or dropped.
            The <strong>center column</strong> shows sections — you can rename them, drag posts between them, or lock sections to preserve them during re-generation.
            The <strong>right column</strong> shows the reading flow — the rhythm of your magazine (text-heavy vs visual vs break).
            You need at least <strong>3 sections</strong> and <strong>5 kept items</strong> to continue.
          </div>
        </div>

        {/* Directive popover */}
        {showDirective && (
          <div className="px-4 py-3 border-b border-base-300/10 bg-base-200/50">
            <label className="text-[11px] text-base-content/50 mb-1 block">Direction for the AI</label>
            <div className="flex gap-2">
              <input value={directive} onChange={e => setDirective(e.target.value)}
                className="input input-bordered input-sm flex-1 text-[12px]"
                placeholder="e.g. Make section 2 shorter, lead with the meditation piece" />
              <button onClick={() => runMutation.mutate()} disabled={isRunning}
                className="btn btn-primary btn-sm text-[12px]">Run</button>
            </div>
            <p className="text-[10px] text-base-content/30 mt-1">
              {(editedState?.sections || []).filter(s => s.locked).length} locked sections will be preserved.
            </p>
          </div>
        )}

        {/* Empty state */}
        {!state && !isRunning && (
          <div className="flex-1 flex items-center justify-center">
            <div className="text-center">
              <Sparkles size={40} className="mx-auto mb-4 text-base-content/10" />
              <p className="text-[14px] text-base-content/40">Click "Generate proposal" to start</p>
              <p className="text-[12px] text-base-content/20 mt-1">Claude will analyze your {items.length} items</p>
            </div>
          </div>
        )}

        {/* Running state */}
        {isRunning && !state && (
          <div className="flex-1 flex items-center justify-center">
            <div className="text-center">
              <Loader2 size={32} className="mx-auto mb-3 animate-spin text-primary" />
              <p className="text-[13px] text-base-content/50">AI is drafting the editorial plan...</p>
            </div>
          </div>
        )}

        {/* Three-column editorial board */}
        {state && (
          <div className="flex flex-1 overflow-hidden">
            {/* Column 1 — Items with decisions */}
            <div className="w-1/3 border-r border-base-300/20 flex flex-col">
              <div className="px-3 py-2 border-b border-base-300/10">
                <div className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Items ({state.decisions.length})</div>
                <div className="text-[9px] text-base-content/25 mt-0.5">Posts from your intake. Override AI decisions with Keep/Drop buttons.</div>
              </div>
              <div className="flex-1 overflow-y-auto">
                {state.decisions.map(d => {
                  return (
                    <div key={d.item_id}
                      className={`px-3 py-2.5 border-b border-base-300/10 cursor-pointer transition-colors ${
                        previewItemId === d.item_id ? 'bg-primary/5' : 'hover:bg-base-300/10'
                      } ${d.decision === 'dropped' ? 'opacity-40' : ''}`}
                      onClick={() => setPreviewItemId(previewItemId === d.item_id ? null : d.item_id)}>
                      <div className="flex items-center gap-2">
                        {d.decision === 'kept' && <CheckCircle size={12} className="text-success shrink-0" />}
                        {d.decision === 'dropped' && <XCircle size={12} className="text-error shrink-0" />}
                        {d.decision === 'trimmed' && <Scissors size={12} className="text-warning shrink-0" />}
                        <span className="text-[12px] text-base-content/70 truncate flex-1">{getItemTitle(d.item_id)}</span>
                        <span className={`badge badge-xs ${d.decision === 'kept' ? 'badge-success' : d.decision === 'dropped' ? 'badge-error' : 'badge-warning'} badge-outline text-[9px]`}>
                          {d.decision}
                        </span>
                      </div>
                      <p className="text-[10px] text-base-content/30 mt-1 line-clamp-1">{d.reason}</p>
                      {d.trim_note && <p className="text-[10px] text-warning/60 mt-0.5">{d.trim_note}</p>}

                      {/* Override buttons */}
                      <div className="flex gap-1 mt-1.5">
                        {d.decision !== 'kept' && (
                          <button onClick={e => { e.stopPropagation(); overrideDecision(d.item_id, 'kept'); }}
                            className="btn btn-ghost btn-xs text-[9px] text-success gap-0.5"><CheckCircle size={9} /> Keep</button>
                        )}
                        {d.decision !== 'dropped' && (
                          <button onClick={e => { e.stopPropagation(); overrideDecision(d.item_id, 'dropped'); }}
                            className="btn btn-ghost btn-xs text-[9px] text-error gap-0.5"><XCircle size={9} /> Drop</button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Column 2 — Sections */}
            <div className="w-1/3 border-r border-base-300/20 flex flex-col">
              <div className="px-3 py-2 border-b border-base-300/10">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Sections ({state.sections.length})</span>
                  <button onClick={addSection} className="btn btn-ghost btn-xs text-[10px] gap-0.5"><Plus size={10} /> Add</button>
                </div>
                <div className="text-[9px] text-base-content/25 mt-0.5">Magazine chapters. Rename titles, lock to preserve during AI re-runs.</div>
              </div>
              <div className="flex-1 overflow-y-auto p-2 space-y-2">
                {state.sections.map((sec, idx) => (
                  <div key={sec.id} className={`p-3 rounded-lg border transition-colors ${sec.locked ? 'border-warning/30 bg-warning/5' : 'border-base-300/30 bg-base-100'}`}>
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-[9px] font-mono text-base-content/20">{idx + 1}</span>
                      <input value={sec.title}
                        onChange={e => editSectionTitle(sec.id, e.target.value)}
                        className="text-[13px] font-medium text-base-content/80 bg-transparent border-none outline-none flex-1 min-w-0" />
                      <button onClick={() => toggleLock(sec.id)}
                        className={`btn btn-ghost btn-xs btn-square ${sec.locked ? 'text-warning' : 'text-base-content/20'}`}
                        title={sec.locked ? 'Unlock (AI can modify)' : 'Lock (preserve on regenerate)'}>
                        {sec.locked ? <Lock size={11} /> : <Unlock size={11} />}
                      </button>
                    </div>

                    {sec.one_line_description && (
                      <p className="text-[10px] text-base-content/40 mb-2">{sec.one_line_description}</p>
                    )}

                    {sec.emotional_register && (
                      <span className="inline-block text-[9px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary mb-2">
                        {sec.emotional_register}
                      </span>
                    )}

                    {/* Items in this section */}
                    <div className="space-y-1 mt-2">
                      {sec.item_ids.map((itemId, itemIdx) => (
                        <div key={itemId} className="flex items-center gap-1 px-2 py-1.5 bg-base-200/50 rounded text-[10px] group">
                          <span className="text-base-content/60 truncate flex-1" title={getItemTitle(itemId)}>
                            {getItemTitle(itemId)}
                          </span>
                          <div className="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onClick={() => moveItemInSection(sec.id, itemId, -1)} disabled={itemIdx === 0}
                              className="btn btn-ghost btn-xs btn-square text-[8px]" title="Move up">↑</button>
                            <button onClick={() => moveItemInSection(sec.id, itemId, 1)} disabled={itemIdx === sec.item_ids.length - 1}
                              className="btn btn-ghost btn-xs btn-square text-[8px]" title="Move down">↓</button>
                            {/* Move to other section dropdown */}
                            <div className="dropdown dropdown-end">
                              <button tabIndex={0} className="btn btn-ghost btn-xs btn-square text-[8px]" title="Move to section">→</button>
                              <ul tabIndex={0} className="dropdown-content z-50 menu menu-xs p-1 shadow bg-base-100 border border-base-300/30 rounded-lg w-40">
                                {state.sections.filter(s => s.id !== sec.id).map(otherSec => (
                                  <li key={otherSec.id}>
                                    <button onClick={() => moveItemToSection(itemId, sec.id, otherSec.id)} className="text-[10px]">
                                      → {otherSec.title}
                                    </button>
                                  </li>
                                ))}
                              </ul>
                            </div>
                            <button onClick={() => removeItemFromSection(itemId, sec.id)}
                              className="btn btn-ghost btn-xs btn-square text-error/50 hover:text-error text-[8px]" title="Remove from section">×</button>
                          </div>
                        </div>
                      ))}
                      {sec.item_ids.length === 0 && (
                        <p className="text-[10px] text-base-content/20 italic px-2 py-2">No items — use → on items in other sections to move them here</p>
                      )}
                    </div>

                    {/* Add unassigned items */}
                    {getUnassignedItems().length > 0 && (
                      <div className="mt-2 pt-2 border-t border-base-300/10">
                        <div className="dropdown w-full">
                          <button tabIndex={0} className="btn btn-ghost btn-xs w-full text-[9px] gap-0.5 text-primary">
                            <Plus size={9} /> Add from unassigned ({getUnassignedItems().length})
                          </button>
                          <ul tabIndex={0} className="dropdown-content z-50 menu menu-xs p-1 shadow bg-base-100 border border-base-300/30 rounded-lg w-full max-h-40 overflow-y-auto">
                            {getUnassignedItems().map(itemId => (
                              <li key={itemId}>
                                <button onClick={() => {
                                  if (!editedState) return;
                                  setEditedState({
                                    ...editedState,
                                    sections: editedState.sections.map(s =>
                                      s.id === sec.id ? { ...s, item_ids: [...s.item_ids, itemId] } : s
                                    ),
                                  });
                                }} className="text-[10px] truncate">{getItemTitle(itemId)}</button>
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    )}

                    {/* Delete section */}
                    {!sec.locked && state.sections.length > 1 && (
                      <button onClick={() => deleteSection(sec.id)}
                        className="btn btn-ghost btn-xs w-full mt-2 text-[9px] text-error/40 hover:text-error">Delete section</button>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Column 3 — Flow rhythm */}
            <div className="w-1/3 flex flex-col">
              <div className="px-3 py-2 border-b border-base-300/10">
                <div className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Reading flow</div>
                <div className="text-[9px] text-base-content/25 mt-0.5">The rhythm of your magazine — alternating dense text and visual breaks.</div>
              </div>
              <div className="flex-1 overflow-y-auto p-3 space-y-2">
                {state.flow.sort((a, b) => a.position - b.position).map((f, idx) => {
                  const sec = state.sections.find(s => s.id === f.section_id);
                  const densityColors: Record<string, string> = {
                    text_heavy: 'bg-blue-500/15 border-blue-500/30 text-blue-500',
                    visual: 'bg-green-500/15 border-green-500/30 text-green-500',
                    break: 'bg-yellow-500/15 border-yellow-500/30 text-yellow-500',
                    reflection: 'bg-purple-500/15 border-purple-500/30 text-purple-500',
                  };
                  const densityHeights: Record<string, string> = {
                    text_heavy: 'h-20', visual: 'h-14', break: 'h-8', reflection: 'h-16',
                  };

                  return (
                    <div key={f.section_id}>
                      <div className={`rounded-lg border p-3 ${densityColors[f.density] || 'bg-base-200/50 border-base-300/30 text-base-content/50'} ${densityHeights[f.density] || 'h-14'} flex flex-col justify-center cursor-grab`}>
                        <div className="text-[11px] font-medium">{sec?.title || f.section_id}</div>
                        <div className="text-[9px] opacity-60 mt-0.5">
                          {f.density.replace('_', ' ')} · {sec?.item_ids.length || 0} items
                        </div>
                      </div>
                      {idx < state.flow.length - 1 && (
                        <div className="flex justify-center py-0.5"><ArrowRight size={10} className="text-base-content/10 rotate-90" /></div>
                      )}
                    </div>
                  );
                })}

                {/* Gaps */}
                {state.gaps.length > 0 && (
                  <div className="mt-4 pt-3 border-t border-base-300/20">
                    <p className="text-[10px] font-medium text-warning/70 uppercase tracking-wider mb-2">Suggested additions</p>
                    {state.gaps.map((g, idx) => (
                      <div key={idx} className="p-2 rounded bg-warning/5 border border-warning/15 text-[11px] mb-1.5">
                        <span className="text-base-content/60">{g.description}</span>
                        {g.suggested_fix && <p className="text-[10px] text-base-content/30 mt-0.5">{g.suggested_fix}</p>}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Bottom bar */}
        <div className="px-4 py-3 border-t border-base-300/20 bg-base-100 flex items-center justify-between shrink-0">
          <div className="text-[11px] text-base-content/40">
            {sectionCount} sections · {keptCount} kept · ~{estPages} pages
            {!canAdvance && sectionCount < 3 && <span className="text-warning ml-2">Need 3+ sections</span>}
            {!canAdvance && keptCount < 5 && sectionCount >= 3 && <span className="text-warning ml-2">Need 5+ kept items</span>}
          </div>
          <div className="flex gap-2">
            <button onClick={() => navigate(`/sites/${siteId}/issue-composer/${issueId}/intake`)}
              className="btn btn-ghost btn-sm text-[12px]">← Intake</button>
            <button onClick={() => advanceMutation.mutate()} disabled={!canAdvance || advanceMutation.isPending}
              className="btn btn-primary btn-sm text-[12px] gap-1">
              {advanceMutation.isPending && <Loader2 size={12} className="animate-spin" />}
              Next: Layout →
            </button>
          </div>
        </div>
      </div>
    </IssueComposerLayout>
  );
}
