interface MagazineToolbarProps {
  onBack?: () => void;
  activeTool: string;
  onSetTool: (t: string) => void;
  zoom: number;
  onZoomChange: (z: number) => void;
  currentPage: number;
  totalPages: number;
  onChangePage: (n: number) => void;
  showGrid: boolean;
  showGuides: boolean;
  showBaseline: boolean;
  onToggleGrid: () => void;
  onToggleGuides: () => void;
  onToggleBaseline: () => void;
  onUndo: () => void;
  onRedo: () => void;
  canUndo: boolean;
  canRedo: boolean;
  onSave: () => void;
  isDirty: boolean;
  isSaving: boolean;
  // Status & preview
  status?: string;
  onStatusChange?: (status: string) => void;
  viewUrl?: string | null;
  pdfUrl?: string | null;
  zipUrl?: string | null;
}

const TOOLS = [
  { key: 'select', label: 'Select', shortcut: 'V', icon: '↖', tip: 'Selection tool (V) — Click to select, drag to move elements. Shift+click for multi-select.' },
  { key: 'text', label: 'Text', shortcut: 'T', icon: 'T', tip: 'Text frame tool (T) — Click and drag on the canvas to create a new text frame.' },
  { key: 'image', label: 'Image', shortcut: 'I', icon: '🖼', tip: 'Image frame tool (I) — Click and drag to create an image frame.' },
  { key: 'rectangle', label: 'Rect', shortcut: 'R', icon: '□', tip: 'Rectangle tool (R) — Click and drag to draw a rectangle.' },
  { key: 'ellipse', label: 'Circle', shortcut: 'E', icon: '○', tip: 'Ellipse tool (E) — Click and drag to draw a circle or ellipse.' },
  { key: 'line', label: 'Line', shortcut: 'L', icon: '╱', tip: 'Line tool (L) — Click and drag to draw a line.' },
];

function Tip({ text, children, pos = 'bottom' }: { text: string; children: React.ReactNode; pos?: 'top' | 'bottom' }) {
  return (
    <div className={`tooltip tooltip-${pos}`} data-tip={text}>
      {children}
    </div>
  );
}

function Divider() {
  return <div className="w-px h-6 bg-base-content/10 mx-1" />;
}

export default function MagazineToolbar({
  activeTool, onSetTool, zoom, onZoomChange, currentPage, totalPages, onChangePage,
  showGrid, showGuides, showBaseline, onToggleGrid, onToggleGuides, onToggleBaseline,
  onBack, onUndo, onRedo, canUndo, canRedo, onSave, isDirty, isSaving,
  status, onStatusChange, viewUrl, pdfUrl, zipUrl,
}: MagazineToolbarProps) {
  const zoomPercent = Math.round(zoom * 100);

  return (
    <div className="flex items-center gap-1 px-3 py-1.5 bg-base-200/80 border-b border-base-content/10">
      {/* Back to dashboard */}
      {onBack && (
        <Tip text="Back to magazine dashboard">
          <button className="btn btn-ghost btn-sm btn-square mr-1" onClick={onBack}>
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
        </Tip>
      )}

      {/* Tool buttons */}
      <div className="flex items-center gap-0.5">
        {TOOLS.map((tool) => (
          <Tip key={tool.key} text={tool.tip}>
            <button
              className={`btn btn-sm gap-1 px-2 text-[11px] font-medium transition-colors ${activeTool === tool.key ? 'btn-primary' : 'btn-ghost text-base-content/60 hover:text-base-content'}`}
              onClick={() => onSetTool(tool.key)}
            >
              <span className="text-[13px]">{tool.icon}</span>
              <span className="hidden sm:inline">{tool.label}</span>
              <kbd className="text-[9px] opacity-40 font-mono">{tool.shortcut}</kbd>
            </button>
          </Tip>
        ))}
      </div>

      <Divider />

      {/* Zoom */}
      <div className="flex items-center gap-0.5">
        <Tip text="Fit page to window — resets zoom to show the full page">
          <button className="btn btn-ghost btn-sm btn-square" onClick={() => onZoomChange(1)}>
            <svg className="w-4 h-4 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
          </button>
        </Tip>
        <Tip text="Zoom out — make the page smaller. Shortcut: Ctrl + minus">
          <button className="btn btn-ghost btn-sm btn-square" onClick={() => onZoomChange(Math.max(zoom - 0.25, 0.1))}>
            <svg className="w-3.5 h-3.5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M20 12H4" />
            </svg>
          </button>
        </Tip>
        <Tip text={`Current zoom: ${zoomPercent}%. Click zoom in/out or use Ctrl+scroll to zoom.`}>
          <span className="text-xs text-base-content/60 tabular-nums w-12 text-center font-mono cursor-default">{zoomPercent}%</span>
        </Tip>
        <Tip text="Zoom in — make the page bigger. Shortcut: Ctrl + plus">
          <button className="btn btn-ghost btn-sm btn-square" onClick={() => onZoomChange(Math.min(zoom + 0.25, 8))}>
            <svg className="w-3.5 h-3.5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </Tip>
      </div>

      <Divider />

      {/* Page navigation */}
      <div className="flex items-center gap-1">
        <Tip text="Go to previous page">
          <button className="btn btn-ghost btn-sm btn-square" onClick={() => onChangePage(currentPage - 1)} disabled={currentPage <= 1}>
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
        </Tip>
        <Tip text="Current page number. Use the page navigator on the left to jump to any page.">
          <span className="text-xs text-base-content/70 whitespace-nowrap tabular-nums cursor-default">Page {currentPage} of {totalPages}</span>
        </Tip>
        <Tip text="Go to next page">
          <button className="btn btn-ghost btn-sm btn-square" onClick={() => onChangePage(currentPage + 1)} disabled={currentPage >= totalPages}>
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </Tip>
      </div>

      <Divider />

      {/* View toggles */}
      <div className="flex items-center gap-0.5">
        <Tip text="Toggle grid — shows a dot grid on the page for alignment. Elements snap to grid when dragging.">
          <button className={`btn btn-xs px-2 font-normal ${showGrid ? 'btn-primary btn-outline' : 'btn-ghost text-base-content/50'}`} onClick={onToggleGrid}>Grid</button>
        </Tip>
        <Tip text="Toggle margin guides — shows page margin boundaries as pink dashed lines.">
          <button className={`btn btn-xs px-2 font-normal ${showGuides ? 'btn-primary btn-outline' : 'btn-ghost text-base-content/50'}`} onClick={onToggleGuides}>Guides</button>
        </Tip>
        <Tip text="Toggle baseline grid — shows horizontal lines for aligning text baselines across columns.">
          <button className={`btn btn-xs px-2 font-normal ${showBaseline ? 'btn-primary btn-outline' : 'btn-ghost text-base-content/50'}`} onClick={onToggleBaseline}>Baseline</button>
        </Tip>
      </div>

      <Divider />

      {/* Undo / Redo */}
      <div className="flex items-center gap-0.5">
        <Tip text="Undo last action (Ctrl+Z)">
          <button className="btn btn-ghost btn-sm btn-square" onClick={onUndo} disabled={!canUndo}>
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4" />
            </svg>
          </button>
        </Tip>
        <Tip text="Redo (Ctrl+Shift+Z)">
          <button className="btn btn-ghost btn-sm btn-square" onClick={onRedo} disabled={!canRedo}>
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4" />
            </svg>
          </button>
        </Tip>
      </div>

      <div className="flex-1" />

      {/* Status dropdown */}
      {onStatusChange && (
        <Tip text="Set issue status — Published issues are viewable publicly">
          <select name="mag-magazinetoolbar-1"
            className={`select select-sm select-bordered text-[11px] font-medium ${
              status === 'published' ? 'border-success text-success' : 'border-warning text-warning'
            }`}
            value={status || 'draft'}
            onChange={(e) => onStatusChange(e.target.value)}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </Tip>
      )}

      {/* View button */}
      {viewUrl && (
        <Tip text="View magazine in viewer">
          <a href={viewUrl} target="_blank" rel="noopener noreferrer"
            className="btn btn-sm btn-ghost gap-1 text-base-content/60 hover:text-success">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
          </a>
        </Tip>
      )}
      {pdfUrl && (
        <Tip text="Export the issue as PDF">
          <a href={pdfUrl} target="_blank" rel="noopener noreferrer"
            className="btn btn-sm btn-ghost gap-1 text-base-content/60 hover:text-primary">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v2a2 2 0 002 2h14a2 2 0 002-2v-2M7 3h7l5 5v3" />
            </svg>
            PDF
          </a>
        </Tip>
      )}
      {zipUrl && (
        <Tip text="Download the standalone magazine (extract anywhere — no CMS needed)">
          <a href={zipUrl} target="_blank" rel="noopener noreferrer"
            className="btn btn-sm btn-ghost gap-1 text-base-content/60 hover:text-primary">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4v10l8 4 8-4V7zM12 3v18M4 7l8 4 8-4" />
            </svg>
            ZIP
          </a>
        </Tip>
      )}

      {/* Save */}
      <Tip text={isDirty ? 'Save changes — you have unsaved edits' : 'All changes saved'}>
        <button className={`btn btn-sm gap-1.5 ${isDirty ? 'btn-primary' : 'btn-ghost text-base-content/50'}`} onClick={onSave} disabled={isSaving || !isDirty}>
          {isSaving ? <span className="loading loading-spinner loading-xs" /> : (
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
          )}
          Save
          {isDirty && !isSaving && <span className="w-1.5 h-1.5 rounded-full bg-warning" />}
        </button>
      </Tip>
    </div>
  );
}
