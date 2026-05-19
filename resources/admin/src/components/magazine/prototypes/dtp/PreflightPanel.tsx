/**
 * M7 DTP Canvas Prototype — Preflight Panel
 *
 * Shows layout/content validation results with click-to-select.
 */
import { AlertTriangle, Info, CheckCircle, XCircle } from 'lucide-react';
import type { PreflightResult, PreflightIssue } from './preflight';

interface Props {
  result: PreflightResult;
  onSelectFrame: (frameId: string) => void;
}

const SEVERITY_ICONS = {
  error: <XCircle size={12} className="text-red-400 shrink-0" />,
  warning: <AlertTriangle size={12} className="text-amber-400 shrink-0" />,
  info: <Info size={12} className="text-blue-400 shrink-0" />,
};

const SEVERITY_BG = {
  error: 'bg-red-500/10 border-red-500/20',
  warning: 'bg-amber-500/10 border-amber-500/20',
  info: 'bg-blue-500/10 border-blue-500/20',
};

const STATUS_CONFIG = {
  pass: { icon: <CheckCircle size={16} />, label: 'Ready', color: 'text-green-400', bg: 'bg-green-500/10' },
  warnings: { icon: <AlertTriangle size={16} />, label: 'Warnings', color: 'text-amber-400', bg: 'bg-amber-500/10' },
  blocked: { icon: <XCircle size={16} />, label: 'Issues', color: 'text-red-400', bg: 'bg-red-500/10' },
};

function IssueRow({ issue, onSelect }: { issue: PreflightIssue; onSelect: () => void }) {
  return (
    <button
      onClick={onSelect}
      className={`w-full text-left px-2.5 py-2 rounded-lg border ${SEVERITY_BG[issue.severity]} hover:brightness-110 transition-all`}
    >
      <div className="flex items-start gap-2">
        {SEVERITY_ICONS[issue.severity]}
        <div className="flex-1 min-w-0">
          <p className="text-[11px] text-neutral-200 leading-snug">{issue.message}</p>
          {issue.suggestion && (
            <p className="text-[9px] text-neutral-400 mt-0.5">{issue.suggestion}</p>
          )}
          {issue.pageNumber && (
            <span className="text-[8px] text-neutral-500 mt-0.5 inline-block">Page {issue.pageNumber}</span>
          )}
        </div>
      </div>
    </button>
  );
}

export function PreflightPanel({ result, onSelectFrame }: Props) {
  const status = STATUS_CONFIG[result.status];

  return (
    <div className="p-3 space-y-3">
      {/* Status header */}
      <div className={`flex items-center gap-3 p-3 rounded-lg ${status.bg}`}>
        <div className={status.color}>{status.icon}</div>
        <div className="flex-1">
          <div className="flex items-center justify-between">
            <span className={`text-sm font-semibold ${status.color}`}>{status.label}</span>
            <span className="text-[20px] font-bold text-neutral-200">{result.score}</span>
          </div>
          <div className="flex items-center gap-3 mt-1 text-[10px] text-neutral-400">
            {result.errors.length > 0 && <span className="text-red-400">{result.errors.length} error{result.errors.length !== 1 ? 's' : ''}</span>}
            {result.warnings.length > 0 && <span className="text-amber-400">{result.warnings.length} warning{result.warnings.length !== 1 ? 's' : ''}</span>}
            {result.info.length > 0 && <span className="text-blue-400">{result.info.length} info</span>}
            {result.issues.length === 0 && <span className="text-green-400">No issues found</span>}
          </div>
        </div>
      </div>

      {/* Score bar */}
      <div className="h-1.5 bg-neutral-700 rounded-full overflow-hidden">
        <div
          className="h-full rounded-full transition-all"
          style={{
            width: `${result.score}%`,
            backgroundColor: result.score >= 80 ? '#22c55e' : result.score >= 50 ? '#f59e0b' : '#ef4444',
          }}
        />
      </div>

      {/* Issues grouped by severity */}
      {result.errors.length > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold text-red-400 uppercase tracking-wider mb-1.5">Errors</h4>
          <div className="space-y-1">
            {result.errors.map(issue => (
              <IssueRow key={issue.id} issue={issue}
                onSelect={() => issue.frameId && onSelectFrame(issue.frameId)} />
            ))}
          </div>
        </div>
      )}

      {result.warnings.length > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-1.5">Warnings</h4>
          <div className="space-y-1">
            {result.warnings.map(issue => (
              <IssueRow key={issue.id} issue={issue}
                onSelect={() => issue.frameId && onSelectFrame(issue.frameId)} />
            ))}
          </div>
        </div>
      )}

      {result.info.length > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold text-blue-400 uppercase tracking-wider mb-1.5">Info</h4>
          <div className="space-y-1">
            {result.info.map(issue => (
              <IssueRow key={issue.id} issue={issue}
                onSelect={() => issue.frameId && onSelectFrame(issue.frameId)} />
            ))}
          </div>
        </div>
      )}

      {/* Legend */}
      <div className="border-t border-neutral-700 pt-2">
        <p className="text-[9px] text-neutral-500">Preflight Lite — checks layout and content. Does not validate image resolution, color profiles, or print readiness.</p>
      </div>
    </div>
  );
}
