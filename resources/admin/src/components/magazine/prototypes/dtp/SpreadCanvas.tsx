/**
 * M2 DTP Canvas Prototype — Spread Canvas
 *
 * Renders pasteboard + page sheets + margin guides + frames.
 * Passes drag/resize callbacks to FrameRenderer.
 */
import type { DtpSpread, DtpFrame } from './mockDocument';
import { FrameRenderer } from './FrameRenderer';

interface Props {
  spread: DtpSpread;
  zoom: number;
  selectedFrameId: string | null;
  onSelectFrame: (id: string | null) => void;
  onUpdateFrame: (id: string, updates: Partial<DtpFrame>) => void;
}

export const PASTEBOARD_PAD = 80;
const PAGE_GAP = 4;
const GUIDE_COLOR = 'rgba(255, 0, 128, 0.3)';

export function SpreadCanvas({ spread, zoom, selectedFrameId, onSelectFrame, onUpdateFrame }: Props) {
  const pages = spread.pages;
  const pageW = pages[0]?.width ?? 595;
  const pageH = pages[0]?.height ?? 842;

  const spreadW = pages.length === 1 ? pageW : pageW * 2 + PAGE_GAP;
  const totalW = spreadW + PASTEBOARD_PAD * 2;
  const totalH = pageH + PASTEBOARD_PAD * 2;

  const handleCanvasClick = (e: React.MouseEvent) => {
    if ((e.target as HTMLElement).dataset.canvasArea) {
      onSelectFrame(null);
    }
  };

  return (
    <div className="flex items-center justify-center min-h-full min-w-full p-8">
      <div
        style={{ width: totalW, height: totalH, transform: `scale(${zoom})`, transformOrigin: '0 0' }}
        className="relative"
        onClick={handleCanvasClick}
        data-canvas-area="pasteboard"
      >
        {/* Pasteboard */}
        <div className="absolute inset-0 bg-neutral-600 rounded" data-canvas-area="pasteboard"
          style={{ boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.05)' }} />

        {/* Pages */}
        {pages.map((page, pageIdx) => {
          const pageX = PASTEBOARD_PAD + (pageIdx * (pageW + PAGE_GAP));
          const pageY = PASTEBOARD_PAD;

          return (
            <div key={page.id} className="absolute" style={{ left: pageX, top: pageY, width: pageW, height: pageH }}>
              <div className="absolute inset-0 bg-white shadow-xl" data-canvas-area="page"
                style={{ backgroundColor: page.backgroundColor, boxShadow: '0 2px 20px rgba(0,0,0,0.3)' }} />

              {/* Margin guides */}
              <div className="absolute pointer-events-none" style={{
                left: page.margins.left, top: page.margins.top,
                right: page.margins.right, bottom: page.margins.bottom,
                border: `1px dashed ${GUIDE_COLOR}`,
              }} />

              {/* Center guides (for snapping reference) */}
              <div className="absolute pointer-events-none" style={{
                left: '50%', top: 0, width: 0, height: '100%',
                borderLeft: '1px dotted rgba(255,0,128,0.12)',
              }} />
              <div className="absolute pointer-events-none" style={{
                left: 0, top: '50%', width: '100%', height: 0,
                borderTop: '1px dotted rgba(255,0,128,0.12)',
              }} />

              <div className="absolute -bottom-5 left-0 right-0 text-center">
                <span className="text-[9px] text-neutral-400 font-mono">{page.pageNumber}</span>
              </div>

              {/* Frames */}
              {spread.frames
                .filter(f => f.pageIndex === pageIdx)
                .sort((a, b) => a.zIndex - b.zIndex)
                .map(frame => (
                  <FrameRenderer
                    key={frame.id}
                    frame={frame}
                    isSelected={frame.id === selectedFrameId}
                    zoom={zoom}
                    onSelect={() => onSelectFrame(frame.id)}
                    onUpdate={(updates) => onUpdateFrame(frame.id, updates)}
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
