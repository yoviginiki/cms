/**
 * M2 DTP Canvas Prototype — Properties Panel
 *
 * Shows document info when nothing selected, editable frame properties when selected.
 */
import { useState, useEffect } from 'react';
import { FileText, ImageIcon, Quote, Hash, Layers } from 'lucide-react';
import type { DtpDocument, DtpFrame, DtpSpread } from './mockDocument';

interface Props {
  document: DtpDocument;
  spread: DtpSpread;
  selectedFrame: DtpFrame | null;
  selectedCount?: number;
  onUpdateFrame?: (id: string, updates: Partial<DtpFrame>) => void;
}

const TYPE_ICONS: Record<DtpFrame['type'], typeof FileText> = {
  text: FileText, image: ImageIcon, quote: Quote, pageNumber: Hash,
};

function PropRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between py-1">
      <span className="text-[10px] text-neutral-400">{label}</span>
      <span className="text-[11px] font-mono text-neutral-200">{value}</span>
    </div>
  );
}

/** Numeric input that validates and calls back on change/blur */
function NumInput({ label, value, onChange, min, max, suffix = 'px' }: {
  label: string; value: number; onChange: (v: number) => void;
  min?: number; max?: number; suffix?: string;
}) {
  const [text, setText] = useState(String(value));

  useEffect(() => { setText(String(value)); }, [value]);

  const commit = () => {
    let n = parseFloat(text);
    if (isNaN(n)) { setText(String(value)); return; }
    if (min !== undefined) n = Math.max(min, n);
    if (max !== undefined) n = Math.min(max, n);
    n = Math.round(n);
    setText(String(n));
    onChange(n);
  };

  return (
    <div>
      <label className="text-[9px] text-neutral-500 mb-0.5 block">{label}</label>
      <div className="flex items-center">
        <input type="text" value={text}
          onChange={e => setText(e.target.value)}
          onBlur={commit}
          onKeyDown={e => { if (e.key === 'Enter') commit(); }}
          className="w-full bg-neutral-700 text-neutral-200 text-[11px] font-mono px-1.5 py-1 rounded border border-neutral-600 focus:border-blue-500 focus:outline-none"
        />
        <span className="text-[9px] text-neutral-500 ml-1 shrink-0">{suffix}</span>
      </div>
    </div>
  );
}

export function PropertiesPanel({ document: doc, spread, selectedFrame, selectedCount = 0, onUpdateFrame }: Props) {
  // Multi-select info
  if (selectedCount > 1) {
    return (
      <div className="p-3 space-y-4">
        <div className="bg-blue-500/10 rounded-lg p-3">
          <h3 className="text-[12px] font-semibold text-blue-300 mb-1">{selectedCount} frames selected</h3>
          <p className="text-[10px] text-neutral-400 leading-relaxed">
            Use toolbar Align buttons to align selected frames. Use Distribute buttons (3+ frames) to space evenly.
          </p>
          <p className="text-[10px] text-neutral-400 mt-2">Arrow keys nudge all selected frames.</p>
        </div>
      </div>
    );
  }

  if (!selectedFrame) {
    return (
      <div className="p-3 space-y-4">
        <div>
          <h3 className="text-[11px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Document</h3>
          <div className="bg-neutral-700/50 rounded-lg p-3 space-y-0.5">
            <PropRow label="Title" value={doc.title} />
            <PropRow label="Subtitle" value={doc.subtitle} />
            <PropRow label="Page size" value={`${doc.pageSize.width} x ${doc.pageSize.height} px`} />
            <PropRow label="Spreads" value={doc.spreads.length} />
            <PropRow label="Total pages" value={doc.spreads.reduce((sum, s) => sum + s.pages.length, 0)} />
            <PropRow label="Total frames" value={doc.spreads.reduce((sum, s) => sum + s.frames.length, 0)} />
          </div>
        </div>

        <div>
          <h3 className="text-[11px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Active Spread</h3>
          <div className="bg-neutral-700/50 rounded-lg p-3 space-y-0.5">
            <PropRow label="Label" value={spread.label} />
            <PropRow label="Pages" value={spread.pages.map(p => p.pageNumber).join(', ')} />
            <PropRow label="Frames" value={spread.frames.length} />
            {spread.pages[0] && (
              <>
                <PropRow label="Margins T" value={`${spread.pages[0].margins.top}px`} />
                <PropRow label="Margins R" value={`${spread.pages[0].margins.right}px`} />
                <PropRow label="Margins B" value={`${spread.pages[0].margins.bottom}px`} />
                <PropRow label="Margins L" value={`${spread.pages[0].margins.left}px`} />
              </>
            )}
          </div>
        </div>

        <div>
          <h3 className="text-[11px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">
            <Layers size={11} className="inline mr-1" />Frames
          </h3>
          <div className="space-y-0.5">
            {[...spread.frames].sort((a, b) => b.zIndex - a.zIndex).map(frame => {
              const Icon = TYPE_ICONS[frame.type];
              return (
                <div key={frame.id} className="flex items-center gap-2 px-2 py-1.5 rounded text-[10px] text-neutral-300 bg-neutral-700/30">
                  <Icon size={11} className="text-neutral-400" />
                  <span className="flex-1 truncate">{frame.label || frame.type}</span>
                  <span className="text-neutral-500 font-mono text-[9px]">z{frame.zIndex}</span>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    );
  }

  const Icon = TYPE_ICONS[selectedFrame.type];
  const update = (updates: Partial<DtpFrame>) => onUpdateFrame?.(selectedFrame.id, updates);

  return (
    <div className="p-3 space-y-4">
      <div className="flex items-center gap-2 mb-1">
        <Icon size={14} className="text-blue-400" />
        <h3 className="text-[12px] font-semibold text-neutral-200">{selectedFrame.label || selectedFrame.type}</h3>
      </div>

      {/* Identity */}
      <div>
        <h4 className="text-[10px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">Identity</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3 space-y-0.5">
          <PropRow label="ID" value={selectedFrame.id} />
          <PropRow label="Type" value={selectedFrame.type} />
          <PropRow label="Page" value={spread.pages[selectedFrame.pageIndex]?.pageNumber ?? '?'} />
        </div>
      </div>

      {/* Transform — editable */}
      <div>
        <h4 className="text-[10px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">Transform</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3">
          <div className="grid grid-cols-2 gap-2">
            <NumInput label="X" value={selectedFrame.x} onChange={v => update({ x: v })} />
            <NumInput label="Y" value={selectedFrame.y} onChange={v => update({ y: v })} />
            <NumInput label="Width" value={selectedFrame.width} onChange={v => update({ width: v })} min={20} />
            <NumInput label="Height" value={selectedFrame.height} onChange={v => update({ height: v })} min={20} />
          </div>
          <div className="grid grid-cols-2 gap-2 mt-2">
            <NumInput label="Rotation" value={selectedFrame.rotation} onChange={v => update({ rotation: v })} min={0} max={360} suffix="deg" />
            <NumInput label="Z-Index" value={selectedFrame.zIndex} onChange={v => update({ zIndex: v })} min={0} max={100} suffix="" />
          </div>
        </div>
      </div>

      {/* Content preview */}
      {selectedFrame.content && (
        <div>
          <h4 className="text-[10px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">Content</h4>
          <div className="bg-neutral-700/50 rounded-lg p-3">
            <p className="text-[10px] text-neutral-300 leading-relaxed whitespace-pre-wrap">
              {selectedFrame.content.slice(0, 200)}{selectedFrame.content.length > 200 ? '...' : ''}
            </p>
          </div>
        </div>
      )}

      {/* Keyboard hint */}
      <div className="bg-neutral-700/30 rounded p-2">
        <p className="text-[9px] text-neutral-500">Arrow keys: nudge 1px | Shift+arrow: 10px</p>
      </div>
    </div>
  );
}
