/**
 * M3 DTP Canvas Prototype — Spread Canvas
 *
 * Adds: rulers, toggleable guides, snap indicator lines.
 */
import { useState } from 'react';
import type { DtpSpread, DtpFrame } from './mockDocument';
import { FrameRenderer } from './FrameRenderer';
import type { SnapLine } from './snapEngine';

interface Props {
  spread: DtpSpread;
  zoom: number;
  selectedIds: string[];
  onSelectFrame: (id: string | null, addToSelection?: boolean) => void;
  onUpdateFrame: (id: string, updates: Partial<DtpFrame>) => void;
  showGuides: boolean;
  showRulers: boolean;
  snapEnabled: boolean;
}

export const PASTEBOARD_PAD = 80;
const PAGE_GAP = 4;
const GUIDE_COLOR = 'rgba(255, 0, 128, 0.3)';
const SNAP_LINE_COLOR = 'rgba(0, 180, 255, 0.8)';
const RULER_SIZE = 20; // px for ruler thickness

export function SpreadCanvas({ spread, zoom, selectedIds, onSelectFrame, onUpdateFrame, showGuides, showRulers, snapEnabled }: Props) {
  const pages = spread.pages;
  const pageW = pages[0]?.width ?? 595;
  const pageH = pages[0]?.height ?? 842;
  const spreadW = pages.length === 1 ? pageW : pageW * 2 + PAGE_GAP;
  const totalW = spreadW + PASTEBOARD_PAD * 2;
  const totalH = pageH + PASTEBOARD_PAD * 2;

  // Active snap lines (set by FrameRenderer during drag)
  const [activeSnapLines, setActiveSnapLines] = useState<SnapLine[]>([]);

  const handleCanvasClick = (e: React.MouseEvent) => {
    if ((e.target as HTMLElement).dataset.canvasArea) {
      onSelectFrame(null);
    }
  };

  return (
    <div className="relative flex items-center justify-center min-h-full min-w-full p-8"
      style={{ paddingLeft: showRulers ? RULER_SIZE + 32 : 32, paddingTop: showRulers ? RULER_SIZE + 32 : 32 }}>

      {/* ─── Rulers ─── */}
      {showRulers && (
        <>
          {/* Horizontal ruler */}
          <div className="absolute left-0 right-0 bg-neutral-800 border-b border-neutral-600 z-20 overflow-hidden"
            style={{ top: 0, height: RULER_SIZE, paddingLeft: RULER_SIZE }}>
            <div style={{ transform: `translateX(${PASTEBOARD_PAD * zoom + 32}px)` }}>
              {Array.from({ length: Math.ceil(spreadW / 50) + 1 }, (_, i) => {
                const pos = i * 50;
                return (
                  <div key={i} className="absolute" style={{ left: pos * zoom, top: 0, height: RULER_SIZE }}>
                    <div className="absolute bottom-0" style={{ left: 0, width: 1, height: pos % 100 === 0 ? 10 : 5, backgroundColor: 'rgba(255,255,255,0.3)' }} />
                    {pos % 100 === 0 && (
                      <span className="absolute text-[7px] text-neutral-400" style={{ left: 2, top: 1 }}>{pos}</span>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Vertical ruler */}
          <div className="absolute top-0 bottom-0 bg-neutral-800 border-r border-neutral-600 z-20 overflow-hidden"
            style={{ left: 0, width: RULER_SIZE, paddingTop: RULER_SIZE }}>
            <div style={{ transform: `translateY(${PASTEBOARD_PAD * zoom + 32}px)` }}>
              {Array.from({ length: Math.ceil(pageH / 50) + 1 }, (_, i) => {
                const pos = i * 50;
                return (
                  <div key={i} className="absolute" style={{ top: pos * zoom, left: 0, width: RULER_SIZE }}>
                    <div className="absolute right-0" style={{ top: 0, height: 1, width: pos % 100 === 0 ? 10 : 5, backgroundColor: 'rgba(255,255,255,0.3)' }} />
                    {pos % 100 === 0 && (
                      <span className="absolute text-[7px] text-neutral-400" style={{ top: 2, left: 2, writingMode: 'vertical-lr' }}>{pos}</span>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Corner square */}
          <div className="absolute z-20 bg-neutral-800 border-r border-b border-neutral-600"
            style={{ top: 0, left: 0, width: RULER_SIZE, height: RULER_SIZE }} />
        </>
      )}

      {/* ─── Canvas ─── */}
      <div
        style={{ width: totalW, height: totalH, transform: `scale(${zoom})`, transformOrigin: '0 0' }}
        className="relative"
        onClick={handleCanvasClick}
        data-canvas-area="pasteboard"
      >
        <div className="absolute inset-0 bg-neutral-600 rounded" data-canvas-area="pasteboard"
          style={{ boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.05)' }} />

        {/* Pages */}
        {pages.map((page, pageIdx) => {
          const pageX = PASTEBOARD_PAD + (pageIdx * (pageW + PAGE_GAP));
          const pageY = PASTEBOARD_PAD;
          const pageFrames = spread.frames.filter(f => f.pageIndex === pageIdx);

          return (
            <div key={page.id} className="absolute" style={{ left: pageX, top: pageY, width: pageW, height: pageH }}>
              <div className="absolute inset-0 bg-white shadow-xl" data-canvas-area="page"
                style={{ backgroundColor: page.backgroundColor, boxShadow: '0 2px 20px rgba(0,0,0,0.3)' }} />

              {/* Guides */}
              {showGuides && (
                <>
                  {/* Margin guides */}
                  <div className="absolute pointer-events-none" style={{
                    left: page.margins.left, top: page.margins.top,
                    right: page.margins.right, bottom: page.margins.bottom,
                    border: `1px dashed ${GUIDE_COLOR}`,
                  }} />
                  {/* Center guides */}
                  <div className="absolute pointer-events-none" style={{
                    left: '50%', top: 0, width: 0, height: '100%',
                    borderLeft: '1px dotted rgba(255,0,128,0.15)',
                  }} />
                  <div className="absolute pointer-events-none" style={{
                    left: 0, top: '50%', width: '100%', height: 0,
                    borderTop: '1px dotted rgba(255,0,128,0.15)',
                  }} />
                </>
              )}

              {/* Snap indicator lines */}
              {activeSnapLines.map((line, i) => (
                <div key={i} className="absolute pointer-events-none" style={
                  line.axis === 'x'
                    ? { left: line.position, top: 0, width: 0, height: '100%', borderLeft: `1px solid ${SNAP_LINE_COLOR}`, zIndex: 9999 }
                    : { left: 0, top: line.position, width: '100%', height: 0, borderTop: `1px solid ${SNAP_LINE_COLOR}`, zIndex: 9999 }
                } />
              ))}

              <div className="absolute -bottom-5 left-0 right-0 text-center">
                <span className="text-[9px] text-neutral-400 font-mono">{page.pageNumber}</span>
              </div>

              {/* Frames */}
              {pageFrames
                .sort((a, b) => a.zIndex - b.zIndex)
                .map(frame => (
                  <FrameRenderer
                    key={frame.id}
                    frame={frame}
                    isSelected={selectedIds.includes(frame.id)}
                    zoom={zoom}
                    page={page}
                    pageFrames={pageFrames}
                    snapEnabled={snapEnabled}
                    onSelect={(addToSel) => onSelectFrame(frame.id, addToSel)}
                    onUpdate={(updates) => onUpdateFrame(frame.id, updates)}
                    onSnapLinesChange={setActiveSnapLines}
                  />
                ))}
            </div>
          );
        })}

        {/* Spine */}
        {pages.length === 2 && (
          <div className="absolute pointer-events-none" style={{
            left: PASTEBOARD_PAD + pageW, top: PASTEBOARD_PAD - 4,
            width: PAGE_GAP, height: pageH + 8,
            background: 'linear-gradient(90deg, rgba(0,0,0,0.15), rgba(0,0,0,0.05), rgba(0,0,0,0.15))',
          }} />
        )}
      </div>
    </div>
  );
}
