/**
 * M1 DTP Canvas Prototype — Properties Panel
 *
 * Shows document info when nothing selected, frame properties when a frame is selected.
 */
import { FileText, ImageIcon, Quote, Hash, Layers } from 'lucide-react';
import type { DtpDocument, DtpFrame, DtpSpread } from './mockDocument';

interface Props {
  document: DtpDocument;
  spread: DtpSpread;
  selectedFrame: DtpFrame | null;
}

const TYPE_ICONS: Record<DtpFrame['type'], typeof FileText> = {
  text: FileText,
  image: ImageIcon,
  quote: Quote,
  pageNumber: Hash,
};

function PropRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between py-1">
      <span className="text-[10px] text-neutral-400">{label}</span>
      <span className="text-[11px] font-mono text-neutral-200">{value}</span>
    </div>
  );
}

export function PropertiesPanel({ document: doc, spread, selectedFrame }: Props) {
  if (!selectedFrame) {
    // Document / spread info
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
            <Layers size={11} className="inline mr-1" />Frames on Spread
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

  // Frame properties
  const Icon = TYPE_ICONS[selectedFrame.type];

  return (
    <div className="p-3 space-y-4">
      <div>
        <div className="flex items-center gap-2 mb-3">
          <Icon size={14} className="text-blue-400" />
          <h3 className="text-[12px] font-semibold text-neutral-200">{selectedFrame.label || selectedFrame.type}</h3>
        </div>
      </div>

      <div>
        <h4 className="text-[10px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">Identity</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3 space-y-0.5">
          <PropRow label="ID" value={selectedFrame.id} />
          <PropRow label="Type" value={selectedFrame.type} />
          <PropRow label="Page" value={spread.pages[selectedFrame.pageIndex]?.pageNumber ?? '?'} />
        </div>
      </div>

      <div>
        <h4 className="text-[10px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">Transform</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3 space-y-0.5">
          <div className="grid grid-cols-2 gap-x-4">
            <PropRow label="X" value={`${selectedFrame.x}px`} />
            <PropRow label="Y" value={`${selectedFrame.y}px`} />
            <PropRow label="W" value={`${selectedFrame.width}px`} />
            <PropRow label="H" value={`${selectedFrame.height}px`} />
          </div>
          <PropRow label="Rotation" value={`${selectedFrame.rotation}deg`} />
          <PropRow label="Z-Index" value={selectedFrame.zIndex} />
        </div>
      </div>

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
    </div>
  );
}
