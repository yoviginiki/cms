/**
 * MAG-DTP-DEBUG-1 — Debug panel for DTP Editor.
 * Event log, save/load diff inspector, live store state viewer.
 * Feature-flagged: only rendered when dtp-debug=1 or ?debug=1.
 */
import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { ChevronDown, ChevronRight, X, Trash2, Copy, Download, AlertTriangle } from 'lucide-react';
import { useMagazineStore } from '@/stores/magazineStore';
import type { ConsistencyResult } from '@/lib/dtpConsistencyChecker';
import DtpConsistencyPanel from './DtpConsistencyPanel';

// ─── Types ───

interface DtpDebugPanelProps {
  lastSavePayload: any;
  lastLoadPayload: any;
  consistencyResult: ConsistencyResult | null;
  onRunConsistencyCheck: () => void;
  onSelectFrame?: (frameId: string) => void;
  onSelectPage?: (pageNumber: number) => void;
  onClose: () => void;
}

type DebugTab = 'log' | 'diff' | 'state' | 'check';

// ─── Important fields for lost-field detection ───

const IMPORTANT_FIELDS = [
  'settings.layoutMode', 'meta.issueSettings', 'meta.viewerSettings',
  'content.text', 'content.html', 'content.src',
  'content.fitMode', 'content.focalPoint', 'content.opacity',
  'typography', 'style',
  'metadata._typography', 'metadata._magType',
  'metadata.threadId', 'metadata.threadOrder',
  'metadata.onMaster', 'metadata.positionMode', 'metadata.spanMode',
  'master_page_id', 'page_index', 'spread_id',
  'frame_type', 'z_index',
] as const;

// ─── Diff engine (no external deps) ───

type DiffKind = 'same' | 'added' | 'removed' | 'changed';

interface DiffNode {
  key: string;
  path: string;
  kind: DiffKind;
  oldValue?: any;
  newValue?: any;
  children?: DiffNode[];
  isImportant?: boolean;
}

function diffObjects(a: any, b: any, key = 'root', parentPath = ''): DiffNode {
  const path = parentPath ? `${parentPath}.${key}` : key;
  const isImportant = IMPORTANT_FIELDS.some(f => path.includes(f));

  if (a === b) return { key, path, kind: 'same', oldValue: a, newValue: b, isImportant };
  if (a == null && b != null) return { key, path, kind: 'added', newValue: b, isImportant };
  if (a != null && b == null) return { key, path, kind: 'removed', oldValue: a, isImportant };

  const aIsObj = typeof a === 'object' && a !== null && !Array.isArray(a);
  const bIsObj = typeof b === 'object' && b !== null && !Array.isArray(b);

  if (aIsObj && bIsObj) {
    const allKeys = new Set([...Object.keys(a), ...Object.keys(b)]);
    const children: DiffNode[] = [];
    let hasChange = false;
    for (const k of allKeys) {
      const child = diffObjects(a[k], b[k], k, path);
      children.push(child);
      if (child.kind !== 'same') hasChange = true;
    }
    return { key, path, kind: hasChange ? 'changed' : 'same', children, isImportant };
  }

  const aIsArr = Array.isArray(a);
  const bIsArr = Array.isArray(b);
  if (aIsArr && bIsArr) {
    const maxLen = Math.max(a.length, b.length);
    const children: DiffNode[] = [];
    let hasChange = a.length !== b.length;
    for (let i = 0; i < maxLen; i++) {
      const child = diffObjects(a[i], b[i], `[${i}]`, path);
      children.push(child);
      if (child.kind !== 'same') hasChange = true;
    }
    return { key, path, kind: hasChange ? 'changed' : 'same', children, isImportant };
  }

  return { key, path, kind: 'changed', oldValue: a, newValue: b, isImportant };
}

/** Count lost (removed) fields in a diff tree, optionally only important ones */
function countLostFields(node: DiffNode, importantOnly = false): number {
  if (node.kind === 'removed' && (!importantOnly || node.isImportant)) return 1;
  if (!node.children) return 0;
  return node.children.reduce((sum, c) => sum + countLostFields(c, importantOnly), 0);
}

/** Collect all changed/removed/added leaf paths */
function collectChangedPaths(node: DiffNode): string[] {
  if (node.kind === 'same') return [];
  if (!node.children) return [node.path];
  return node.children.flatMap(c => collectChangedPaths(c));
}

// ─── Collapsible JSON tree ───

function JsonTree({ data, label, depth = 0 }: { data: any; label?: string; depth?: number }) {
  const [open, setOpen] = useState(depth < 2);

  if (data === null || data === undefined) {
    return <span className="text-base-content/30">{label && <span className="text-info">{label}: </span>}null</span>;
  }

  if (typeof data !== 'object') {
    const color = typeof data === 'string' ? 'text-success' : typeof data === 'number' ? 'text-warning' : 'text-accent';
    return (
      <span>
        {label && <span className="text-info">{label}: </span>}
        <span className={color}>{JSON.stringify(data)}</span>
      </span>
    );
  }

  const isArray = Array.isArray(data);
  const keys = isArray ? data.map((_: any, i: number) => String(i)) : Object.keys(data);
  const preview = isArray ? `Array(${data.length})` : `{${keys.length} keys}`;

  return (
    <div style={{ paddingLeft: depth > 0 ? 12 : 0 }}>
      <span className="cursor-pointer select-none inline-flex items-center gap-0.5" onClick={() => setOpen(!open)}>
        {open ? <ChevronDown size={10} /> : <ChevronRight size={10} />}
        {label && <span className="text-info">{label}: </span>}
        <span className="text-base-content/40">{preview}</span>
      </span>
      {open && (
        <div>
          {keys.map((k: string) => (
            <div key={k}>
              <JsonTree data={isArray ? data[Number(k)] : data[k]} label={k} depth={depth + 1} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Diff tree renderer ───

function DiffTree({ node, depth = 0 }: { node: DiffNode; depth?: number }) {
  const [open, setOpen] = useState(depth < 2);

  const bgClass =
    node.kind === 'added' ? 'bg-success/10' :
    node.kind === 'removed' ? 'bg-error/10' :
    node.kind === 'changed' && !node.children ? 'bg-warning/10' : '';

  const textClass =
    node.kind === 'added' ? 'text-success' :
    node.kind === 'removed' ? 'text-error' :
    node.kind === 'changed' ? 'text-warning' : 'text-base-content/50';

  if (node.children) {
    const changedCount = node.children.filter(c => c.kind !== 'same').length;
    return (
      <div style={{ paddingLeft: depth > 0 ? 12 : 0 }}>
        <span className={`cursor-pointer select-none inline-flex items-center gap-0.5 ${textClass}`} onClick={() => setOpen(!open)}>
          {open ? <ChevronDown size={10} /> : <ChevronRight size={10} />}
          <span className="font-medium">{node.key}</span>
          {node.isImportant && <span className="text-error text-[8px] ml-0.5">!</span>}
          {changedCount > 0 && <span className="text-warning text-[9px] ml-1">({changedCount} changes)</span>}
        </span>
        {open && (
          <div>
            {node.children.map((child, i) => (
              <DiffTree key={`${child.key}-${i}`} node={child} depth={depth + 1} />
            ))}
          </div>
        )}
      </div>
    );
  }

  return (
    <div style={{ paddingLeft: depth > 0 ? 12 : 0 }} className={`${bgClass} rounded px-1`}>
      <span className={textClass}>
        <span className="font-medium">{node.key}</span>
        {node.isImportant && <span className="text-error text-[8px] ml-0.5">!</span>}
        {node.kind === 'added' && <span>: <span className="text-success">{JSON.stringify(node.newValue)}</span> <span className="text-[9px] text-success/60">(added)</span></span>}
        {node.kind === 'removed' && <span>: <span className="text-error line-through">{JSON.stringify(node.oldValue)}</span> <span className="text-[9px] text-error/60">(removed)</span></span>}
        {node.kind === 'changed' && (
          <span>: <span className="text-error line-through">{JSON.stringify(node.oldValue)}</span> {' \u2192 '} <span className="text-success">{JSON.stringify(node.newValue)}</span></span>
        )}
        {node.kind === 'same' && <span>: <span className="text-base-content/30">{JSON.stringify(node.oldValue)}</span></span>}
      </span>
    </div>
  );
}

// ─── Main Panel ───

export default function DtpDebugPanel({ lastSavePayload, lastLoadPayload, consistencyResult, onRunConsistencyCheck, onSelectFrame, onSelectPage, onClose }: DtpDebugPanelProps) {
  const [tab, setTab] = useState<DebugTab>('log');
  const [panelHeight, setPanelHeight] = useState(300);
  const dragRef = useRef<{ startY: number; startH: number } | null>(null);
  const logEndRef = useRef<HTMLDivElement>(null);
  const store = useMagazineStore();

  // Auto-scroll log
  useEffect(() => {
    if (tab === 'log') logEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [store.debugLog.length, tab]);

  // Resize drag handler
  const onDragStart = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    dragRef.current = { startY: e.clientY, startH: panelHeight };
    const onMove = (ev: MouseEvent) => {
      if (!dragRef.current) return;
      const delta = dragRef.current.startY - ev.clientY;
      const maxH = window.innerHeight * 0.5;
      setPanelHeight(Math.max(150, Math.min(maxH, dragRef.current.startH + delta)));
    };
    const onUp = () => {
      dragRef.current = null;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [panelHeight]);

  const formatTs = (ts: number) => {
    const d = new Date(ts);
    return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}:${d.getSeconds().toString().padStart(2, '0')}.${d.getMilliseconds().toString().padStart(3, '0')}`;
  };

  // Counts and status
  const errorCount = store.debugLog.filter(e => e.severity === 'error').length;
  const warnCount = store.debugLog.filter(e => e.severity === 'warn').length;
  const lastEvent = store.debugLog.length > 0 ? store.debugLog[store.debugLog.length - 1] : null;
  const lastSaveEvent = [...store.debugLog].reverse().find(e => e.action.startsWith('save:'));
  const lastLoadEvent = [...store.debugLog].reverse().find(e => e.action.startsWith('load:'));
  const selectedEl = store.selectedIds[0]
    ? store.pages.flatMap(p => p.elements).find(e => e.id === store.selectedIds[0])
    : null;

  // Diff result + lost field count
  const diffResult = useMemo(() =>
    lastSavePayload && lastLoadPayload
      ? diffObjects(lastLoadPayload, lastSavePayload, 'document')
      : null,
    [lastSavePayload, lastLoadPayload],
  );

  const lostFieldCount = diffResult ? countLostFields(diffResult) : 0;
  const importantLostCount = diffResult ? countLostFields(diffResult, true) : 0;

  // Store state snapshot (exclude heavy data)
  const storeSnapshot = {
    pageCount: store.pages.length,
    currentPageNumber: store.currentPageNumber,
    selectedIds: store.selectedIds,
    activeTool: store.activeTool,
    isDirty: store.isDirty,
    isSaving: store.isSaving,
    undoStackSize: store.undoStack.length,
    redoStackSize: store.redoStack.length,
    zoom: store.zoom,
    viewMode: store.viewMode,
    showGrid: store.showGrid,
    showGuides: store.showGuides,
    showBaseline: store.showBaseline,
    issueSettings: store.issueSettings,
    editingMasterId: store.editingMasterId,
    pages: store.pages.map(p => ({
      id: p.id,
      pageNumber: p.pageNumber,
      isMaster: p.isMaster,
      elementCount: p.elements.length,
      pageSize: p.pageSize,
      margins: p.margins,
      elements: p.elements.map(e => ({
        id: e.id,
        type: e.type,
        name: e.name,
        x: Math.round(e.x),
        y: Math.round(e.y),
        w: Math.round(e.width),
        h: Math.round(e.height),
        locked: e.locked,
        visible: e.visible,
        threadId: e.threadId,
      })),
    })),
  };

  // Copy to clipboard
  const copyToClipboard = (data: any, label: string) => {
    navigator.clipboard.writeText(JSON.stringify(data, null, 2)).catch(() => {});
    store.pushDebugLog('debug:copy', 'panel', { label });
  };

  // Export as JSON file
  const exportJson = (data: any, filename: string) => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    store.pushDebugLog('debug:export', 'panel', { filename });
  };

  return (
    <div className="bg-base-300 border-t border-base-content/10 flex flex-col shrink-0" style={{ height: panelHeight }}>
      {/* Drag handle */}
      <div
        className="h-1.5 cursor-ns-resize bg-base-content/5 hover:bg-primary/20 transition-colors flex items-center justify-center shrink-0"
        onMouseDown={onDragStart}
      >
        <div className="w-8 h-0.5 rounded bg-base-content/20" />
      </div>

      {/* Header */}
      <div className="flex items-center justify-between px-3 py-1 border-b border-base-content/10 shrink-0">
        <div className="flex items-center gap-2">
          <span className="text-[9px] font-mono font-bold text-warning/80 tracking-wider">DEBUG</span>

          {/* Status summary */}
          <span className="text-[9px] text-base-content/30">
            P{store.currentPageNumber}
            {selectedEl && <> | <span className="text-info/60">{selectedEl.type}</span> <span className="text-base-content/15">{selectedEl.id.slice(0, 6)}</span></>}
            {lastSaveEvent && <> | <span className={lastSaveEvent.action === 'save:success' ? 'text-success/60' : lastSaveEvent.action === 'save:fail' ? 'text-error/60' : 'text-warning/60'}>{lastSaveEvent.action}</span></>}
            {lastLoadEvent && <> | <span className="text-info/60">{lastLoadEvent.action}</span></>}
            {lastEvent && <> | <span className="text-base-content/20">{lastEvent.action}</span></>}
          </span>

          {/* Status counts */}
          {errorCount > 0 && (
            <span className="text-[9px] bg-error/20 text-error px-1.5 py-0.5 rounded font-medium">{errorCount} errors</span>
          )}
          {warnCount > 0 && (
            <span className="text-[9px] bg-warning/20 text-warning px-1.5 py-0.5 rounded font-medium">{warnCount} warns</span>
          )}
          {importantLostCount > 0 && (
            <span className="text-[9px] bg-error/20 text-error px-1.5 py-0.5 rounded font-medium flex items-center gap-0.5">
              <AlertTriangle size={8} /> {importantLostCount} lost fields
            </span>
          )}

          <div className="flex gap-0.5">
            {([
              { key: 'log' as DebugTab, label: `Event Log (${store.debugLog.length})` },
              { key: 'diff' as DebugTab, label: `Diff${lostFieldCount > 0 ? ` (${lostFieldCount})` : ''}` },
              { key: 'check' as DebugTab, label: `Check${consistencyResult ? ` ${consistencyResult.status === 'pass' ? '\u2713' : consistencyResult.status === 'fail' ? `\u2717${consistencyResult.summary.failures}` : `\u26A0${consistencyResult.summary.warnings}`}` : ''}` },
              { key: 'state' as DebugTab, label: 'Store State' },
            ]).map(t => (
              <button
                key={t.key}
                onClick={() => setTab(t.key)}
                className={`px-2 py-0.5 text-[10px] rounded transition-colors ${
                  tab === t.key ? 'bg-primary/20 text-primary font-medium' : 'text-base-content/40 hover:text-base-content/60'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>
        </div>
        <div className="flex items-center gap-1">
          {tab === 'log' && (
            <>
              <button onClick={() => copyToClipboard(store.debugLog, 'event log')} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Copy log">
                <Copy size={10} /> Copy
              </button>
              <button onClick={() => exportJson(store.debugLog, `dtp-debug-log-${Date.now()}.json`)} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Export log">
                <Download size={10} /> Export
              </button>
              <button onClick={() => store.clearDebugLog()} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-error" title="Clear log">
                <Trash2 size={10} /> Clear
              </button>
            </>
          )}
          {tab === 'diff' && diffResult && (
            <>
              <button onClick={() => copyToClipboard({ load: lastLoadPayload, save: lastSavePayload, changedPaths: collectChangedPaths(diffResult) }, 'diff')} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Copy diff">
                <Copy size={10} /> Copy
              </button>
              <button onClick={() => exportJson({ load: lastLoadPayload, save: lastSavePayload, changedPaths: collectChangedPaths(diffResult) }, `dtp-diff-${Date.now()}.json`)} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Export diff">
                <Download size={10} /> Export
              </button>
            </>
          )}
          {tab === 'check' && consistencyResult && (
            <>
              <button onClick={() => copyToClipboard(consistencyResult, 'consistency report')} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Copy report">
                <Copy size={10} /> Copy
              </button>
              <button onClick={() => exportJson(consistencyResult, `dtp-consistency-${Date.now()}.json`)} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Export report">
                <Download size={10} /> Export
              </button>
            </>
          )}
          {tab === 'state' && (
            <button onClick={() => copyToClipboard(storeSnapshot, 'store state')} className="btn btn-ghost btn-xs gap-1 text-base-content/30 hover:text-info" title="Copy state">
              <Copy size={10} /> Copy
            </button>
          )}
          <button onClick={onClose} className="btn btn-ghost btn-xs text-base-content/30 hover:text-error">
            <X size={12} />
          </button>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto font-mono text-[10px] p-2">
        {tab === 'log' && (
          <div>
            {store.debugLog.length === 0 ? (
              <div className="text-base-content/20 text-center py-4">No events logged yet. Interact with the editor to see actions.</div>
            ) : (
              store.debugLog.map((entry, i) => (
                <LogEntry key={`${entry.ts}-${i}`} entry={entry} formatTs={formatTs} />
              ))
            )}
            <div ref={logEndRef} />
          </div>
        )}

        {tab === 'diff' && (
          <div>
            {!diffResult ? (
              <div className="text-base-content/20 text-center py-4">
                Save the document to capture a payload, then reload to compare round-trip fidelity.
                {lastLoadPayload && !lastSavePayload && <div className="mt-1 text-info/40">Load payload captured. Save to generate diff.</div>}
              </div>
            ) : (
              <div>
                {/* Lost field warning */}
                {importantLostCount > 0 && (
                  <div className="flex items-center gap-2 px-2 py-1.5 mb-2 bg-error/10 border border-error/20 rounded text-error text-[10px]">
                    <AlertTriangle size={12} />
                    <span><strong>{importantLostCount} important field(s)</strong> lost during save round-trip. These may cause data loss on reload.</span>
                  </div>
                )}
                <div className="flex gap-4 mb-2 text-[9px]">
                  <span className="text-success flex items-center gap-1"><span className="w-2 h-2 rounded bg-success/30" /> Added in save</span>
                  <span className="text-error flex items-center gap-1"><span className="w-2 h-2 rounded bg-error/30" /> Removed ({lostFieldCount})</span>
                  <span className="text-warning flex items-center gap-1"><span className="w-2 h-2 rounded bg-warning/30" /> Changed</span>
                </div>
                <DiffTree node={diffResult} />
              </div>
            )}
          </div>
        )}

        {tab === 'check' && (
          <DtpConsistencyPanel
            result={consistencyResult}
            onRun={onRunConsistencyCheck}
            onSelectFrame={onSelectFrame}
            onSelectPage={onSelectPage}
            onCopy={copyToClipboard}
            onExport={exportJson}
          />
        )}

        {tab === 'state' && (
          <JsonTree data={storeSnapshot} label="magazineStore" />
        )}
      </div>
    </div>
  );
}

// ─── Log entry (expandable) ───

function LogEntry({ entry, formatTs }: { entry: { ts: number; action: string; severity: string; source: string; selectedId?: string | null; elementType?: string | null; pageNumber?: number | null; pageId?: string | null; detail?: any }; formatTs: (ts: number) => string }) {
  const [open, setOpen] = useState(false);
  const hasDetail = entry.detail != null;

  const severityClass =
    entry.severity === 'error' ? 'text-error' :
    entry.severity === 'warn' ? 'text-warning' : 'text-base-content/20';

  const severityBg =
    entry.severity === 'error' ? 'bg-error/5' :
    entry.severity === 'warn' ? 'bg-warning/5' : '';

  return (
    <div className={`border-b border-base-content/5 py-0.5 ${severityBg}`}>
      <div
        className={`flex items-center gap-2 ${hasDetail ? 'cursor-pointer hover:bg-base-content/5' : ''} rounded px-1`}
        onClick={() => hasDetail && setOpen(!open)}
      >
        <span className={`w-20 shrink-0 ${severityClass}`}>{formatTs(entry.ts)}</span>
        <span className="text-base-content/20 w-12 shrink-0 text-[9px]">{entry.source}</span>
        {entry.pageNumber != null && <span className="text-base-content/15 w-6 shrink-0 text-[9px]">P{entry.pageNumber}</span>}
        <span className="text-primary font-medium">{entry.action}</span>
        {entry.elementType && <span className="text-base-content/20 text-[9px]">[{entry.elementType}]</span>}
        {entry.selectedId && <span className="text-base-content/15 text-[8px]">{entry.selectedId.slice(0, 8)}</span>}
        {hasDetail && (
          open ? <ChevronDown size={8} className="text-base-content/20" /> : <ChevronRight size={8} className="text-base-content/20" />
        )}
      </div>
      {open && hasDetail && (
        <div className="pl-24 py-1 text-base-content/40">
          <pre className="whitespace-pre-wrap break-all">{JSON.stringify(entry.detail, null, 2)}</pre>
        </div>
      )}
    </div>
  );
}
