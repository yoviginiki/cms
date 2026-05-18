/**
 * M1 DTP Canvas Prototype — Spread Canvas
 *
 * Renders pasteboard + page sheets + margin guides + frames.
 */
import type { DtpSpread } from './mockDocument';
import { FrameRenderer } from './FrameRenderer';

interface Props {
  spread: DtpSpread;
  zoom: number;
  selectedFrameId: string | null;
  onSelectFrame: (id: string | null) => void;
}

const PASTEBOARD_PAD = 80;  // px around pages
const PAGE_GAP = 4;         // px between pages in spread
const GUIDE_COLOR = 'rgba(255, 0, 128, 0.3)';

export function SpreadCanvas({ spread, zoom, selectedFrameId, onSelectFrame }: Props) {
  const pages = spread.pages;
  const pageW = pages[0]?.width ?? 595;
  const pageH = pages[0]?.height ?? 842;

  // Total spread dimensions
  const spreadW = pages.length === 1 ? pageW : pageW * 2 + PAGE_GAP;
  const spreadH = pageH;

  // Pasteboard dimensions
  const totalW = spreadW + PASTEBOARD_PAD * 2;
  const totalH = spreadH + PASTEBOARD_PAD * 2;

  const handleCanvasClick = (e: React.MouseEvent) => {
    // Only deselect if clicking the pasteboard/page background (not a frame)
    if ((e.target as HTMLElement).dataset.canvasArea) {
      onSelectFrame(null);
    }
  };

  return (
    <div className="flex items-center justify-center min-h-full min-w-full p-8">
      <div
        style={{
          width: totalW,
          height: totalH,
          transform: `scale(${zoom})`,
          transformOrigin: '0 0',
        }}
        className="relative"
        onClick={handleCanvasClick}
        data-canvas-area="pasteboard"
      >
        {/* Pasteboard background */}
        <div className="absolute inset-0 bg-neutral-600 rounded"
          data-canvas-area="pasteboard"
          style={{ boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.05)' }} />

        {/* Pages */}
        {pages.map((page, pageIdx) => {
          const pageX = PASTEBOARD_PAD + (pageIdx * (pageW + PAGE_GAP));
          const pageY = PASTEBOARD_PAD;

          return (
            <div key={page.id} className="absolute" style={{ left: pageX, top: pageY, width: pageW, height: pageH }}>
              {/* Page sheet */}
              <div className="absolute inset-0 bg-white shadow-xl" data-canvas-area="page"
                style={{ backgroundColor: page.backgroundColor, boxShadow: '0 2px 20px rgba(0,0,0,0.3)' }} />

              {/* Margin guides */}
              <div className="absolute pointer-events-none" style={{
                left: page.margins.left,
                top: page.margins.top,
                right: page.margins.right,
                bottom: page.margins.bottom,
                border: `1px dashed ${GUIDE_COLOR}`,
              }} />

              {/* Corner ticks for bleed area visualization */}
              {[
                { left: page.margins.left, top: 0 },
                { right: page.margins.right, top: 0 },
                { left: page.margins.left, bottom: 0 },
                { right: page.margins.right, bottom: 0 },
              ].map((pos, i) => (
                <div key={i} className="absolute pointer-events-none" style={{
                  ...pos,
                  width: 1,
                  height: 8,
                  backgroundColor: GUIDE_COLOR,
                }} />
              ))}

              {/* Page number label */}
              <div className="absolute -bottom-5 left-0 right-0 text-center">
                <span className="text-[9px] text-neutral-400 font-mono">{page.pageNumber}</span>
              </div>

              {/* Frames on this page */}
              {spread.frames
                .filter(f => f.pageIndex === pageIdx)
                .sort((a, b) => a.zIndex - b.zIndex)
                .map(frame => (
                  <FrameRenderer
                    key={frame.id}
                    frame={frame}
                    isSelected={frame.id === selectedFrameId}
                    onSelect={() => onSelectFrame(frame.id)}
                  />
                ))}
            </div>
          );
        })}

        {/* Spine indicator for two-page spreads */}
        {pages.length === 2 && (
          <div className="absolute pointer-events-none" style={{
            left: PASTEBOARD_PAD + pageW,
            top: PASTEBOARD_PAD - 4,
            width: PAGE_GAP,
            height: pageH + 8,
            background: 'linear-gradient(90deg, rgba(0,0,0,0.15), rgba(0,0,0,0.05), rgba(0,0,0,0.15))',
          }} />
        )}
      </div>
    </div>
  );
}
