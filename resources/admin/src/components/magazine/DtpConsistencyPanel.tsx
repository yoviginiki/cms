/**
 * MAG-DTP-CHECK-1 — Consistency check UI panel.
 * Renders inside the DTP debug panel as a tab.
 */
import { useState } from 'react';
import { AlertTriangle, CheckCircle, Info, Copy, Download, Play, ChevronDown, ChevronRight } from 'lucide-react';
import type { ConsistencyResult, ConsistencyIssue, IssueSeverity } from '@/lib/dtpConsistencyChecker';

interface DtpConsistencyPanelProps {
  result: ConsistencyResult | null;
  onRun: () => void;
  onSelectFrame?: (frameId: string) => void;
  onSelectPage?: (pageNumber: number) => void;
  onCopy: (data: any, label: string) => void;
  onExport: (data: any, filename: string) => void;
}

export default function DtpConsistencyPanel({ result, onRun, onSelectFrame, onSelectPage, onCopy, onExport }: DtpConsistencyPanelProps) {

  if (!result) {
    return (
      <div className="text-center py-6">
        <p className="text-base-content/30 text-[11px] mb-3">No consistency check run yet.</p>
        <button onClick={onRun} className="btn btn-sm btn-primary gap-1">
          <Play size={12} /> Run Consistency Check
        </button>
      </div>
    );
  }

  const grouped = {
    error: result.issues.filter(i => i.severity === 'error'),
    warning: result.issues.filter(i => i.severity === 'warning'),
    info: result.issues.filter(i => i.severity === 'info'),
  };

  const formatTime = (ts: number) => {
    const d = new Date(ts);
    return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}:${d.getSeconds().toString().padStart(2, '0')}`;
  };

  return (
    <div className="space-y-3">
      {/* Status badge + summary */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <StatusBadge status={result.status} />
          <span className="text-[9px] text-base-content/30">
            Checked {result.summary.checkedPaths} paths at {formatTime(result.checkedAt)}
          </span>
        </div>
        <div className="flex items-center gap-1">
          <button onClick={onRun} className="btn btn-ghost btn-xs gap-1 text-primary" title="Re-run check">
            <Play size={10} /> Re-run
          </button>
          <button onClick={() => onCopy(result, 'consistency report')} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Copy report">
            <Copy size={10} />
          </button>
          <button onClick={() => onExport(result, `dtp-consistency-${Date.now()}.json`)} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Export report">
            <Download size={10} />
          </button>
        </div>
      </div>

      {/* Summary counts */}
      <div className="flex gap-3 text-[10px]">
        <span className={result.summary.failures > 0 ? 'text-error font-medium' : 'text-base-content/30'}>
          {result.summary.failures} errors
        </span>
        <span className={result.summary.warnings > 0 ? 'text-warning font-medium' : 'text-base-content/30'}>
          {result.summary.warnings} warnings
        </span>
        <span className={result.summary.lostFields > 0 ? 'text-error font-medium' : 'text-base-content/30'}>
          {result.summary.lostFields} lost fields
        </span>
        <span className={result.summary.viewerMismatches > 0 ? 'text-warning font-medium' : 'text-base-content/30'}>
          {result.summary.viewerMismatches} viewer mismatches
        </span>
        <span className={result.summary.savePayloadMismatches > 0 ? 'text-warning font-medium' : 'text-base-content/30'}>
          {result.summary.savePayloadMismatches} save mismatches
        </span>
      </div>

      {/* Viewer partial warning */}
      {result.viewerCheckPartial && (
        <div className="text-[9px] text-base-content/20 bg-base-200/30 rounded px-2 py-1">
          Viewer render model unavailable; checking editor vs save/load only.
        </div>
      )}

      {/* Issue groups */}
      {result.issues.length === 0 ? (
        <div className="text-center py-4 text-success/60 text-[11px] flex items-center justify-center gap-1">
          <CheckCircle size={14} /> All checked paths are consistent.
        </div>
      ) : (
        <div className="space-y-2">
          {(['error', 'warning', 'info'] as IssueSeverity[]).map(sev => {
            const items = grouped[sev];
            if (items.length === 0) return null;
            return (
              <IssueGroup
                key={sev}
                severity={sev}
                issues={items}
                onSelectFrame={onSelectFrame}
                onSelectPage={onSelectPage}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  if (status === 'pass') {
    return <span className="text-[10px] bg-success/20 text-success px-2 py-0.5 rounded font-bold flex items-center gap-1"><CheckCircle size={10} /> PASS</span>;
  }
  if (status === 'warning') {
    return <span className="text-[10px] bg-warning/20 text-warning px-2 py-0.5 rounded font-bold flex items-center gap-1"><AlertTriangle size={10} /> WARNINGS</span>;
  }
  return <span className="text-[10px] bg-error/20 text-error px-2 py-0.5 rounded font-bold flex items-center gap-1"><AlertTriangle size={10} /> FAIL</span>;
}

function IssueGroup({ severity, issues, onSelectFrame, onSelectPage }: {
  severity: IssueSeverity;
  issues: ConsistencyIssue[];
  onSelectFrame?: (id: string) => void;
  onSelectPage?: (n: number) => void;
}) {
  const [open, setOpen] = useState(true);
  const color = severity === 'error' ? 'text-error' : severity === 'warning' ? 'text-warning' : 'text-info';
  const label = severity === 'error' ? 'Errors' : severity === 'warning' ? 'Warnings' : 'Info';

  return (
    <div>
      <button onClick={() => setOpen(!open)} className={`flex items-center gap-1 ${color} text-[10px] font-medium`}>
        {open ? <ChevronDown size={10} /> : <ChevronRight size={10} />}
        {label} ({issues.length})
      </button>
      {open && (
        <div className="ml-3 mt-1 space-y-1">
          {issues.map(issue => (
            <IssueRow key={issue.id} issue={issue} onSelectFrame={onSelectFrame} onSelectPage={onSelectPage} />
          ))}
        </div>
      )}
    </div>
  );
}

function IssueRow({ issue, onSelectFrame, onSelectPage }: {
  issue: ConsistencyIssue;
  onSelectFrame?: (id: string) => void;
  onSelectPage?: (n: number) => void;
}) {
  const [expanded, setExpanded] = useState(false);

  const severityBg =
    issue.severity === 'error' ? 'bg-error/5 border-error/10' :
    issue.severity === 'warning' ? 'bg-warning/5 border-warning/10' :
    'bg-info/5 border-info/10';

  const severityIcon =
    issue.severity === 'error' ? <AlertTriangle size={9} className="text-error shrink-0 mt-0.5" /> :
    issue.severity === 'warning' ? <AlertTriangle size={9} className="text-warning shrink-0 mt-0.5" /> :
    <Info size={9} className="text-info shrink-0 mt-0.5" />;

  return (
    <div className={`border rounded p-1.5 ${severityBg} cursor-pointer`} onClick={() => setExpanded(!expanded)}>
      <div className="flex items-start gap-1.5">
        {severityIcon}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-1.5 flex-wrap">
            <span className="text-[10px] font-medium text-base-content/70">{issue.label}</span>
            <span className="text-[8px] text-base-content/20 font-mono">{issue.code}</span>
            {issue.relatedFrameId && onSelectFrame && (
              <button
                onClick={(e) => { e.stopPropagation(); onSelectFrame(issue.relatedFrameId!); }}
                className="text-[8px] text-primary hover:underline"
              >
                select frame
              </button>
            )}
            {issue.relatedPageId && onSelectPage && !issue.relatedFrameId && (
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  // Extract page index from path like "pages[2]..." and convert to 1-based
                  const match = issue.path.match(/pages\[(\d+)\]/);
                  const pageNum = match ? parseInt(match[1], 10) + 1 : 1;
                  onSelectPage(pageNum);
                }}
                className="text-[8px] text-primary hover:underline"
              >
                go to page
              </button>
            )}
          </div>
          <div className="text-[9px] text-base-content/40 mt-0.5">{issue.message}</div>
        </div>
      </div>
      {expanded && (
        <div className="mt-1.5 ml-4 text-[9px] space-y-0.5 font-mono">
          <div className="text-base-content/20">{issue.path}</div>
          {issue.editorValue !== undefined && <div>Editor: <span className="text-info">{JSON.stringify(issue.editorValue)}</span></div>}
          {issue.payloadValue !== undefined && <div>Payload: <span className="text-warning">{JSON.stringify(issue.payloadValue)}</span></div>}
          {issue.loadedValue !== undefined && <div>Loaded: <span className="text-error">{JSON.stringify(issue.loadedValue)}</span></div>}
          {issue.viewerValue !== undefined && <div>Viewer: <span className="text-accent">{JSON.stringify(issue.viewerValue)}</span></div>}
          <div className="text-base-content/30 italic mt-1">{issue.suggestion}</div>
        </div>
      )}
    </div>
  );
}
