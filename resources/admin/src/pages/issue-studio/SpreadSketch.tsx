import type React from 'react';
import { sketchFor, type SketchBlock } from './patternGeometry';

/**
 * Renders a pattern's deterministic wireframe as a small SVG — image mass as
 * solid blocks, text as line bundles, headlines as heavy bars.
 */
export default function SpreadSketch({ pattern, className }: { pattern: string; className?: string }) {
  const sketch = sketchFor(pattern);
  const width = sketch.cover ? 595 : 1190;

  return (
    <svg viewBox={`0 0 ${width} 842`} className={className} role="img" aria-label={`${pattern} layout sketch`}>
      <rect x={0} y={0} width={width} height={842} className="fill-base-100" />
      {!sketch.cover && (
        <line x1={595} y1={0} x2={595} y2={842} className="stroke-base-content/15" strokeWidth={2} strokeDasharray="8 8" />
      )}
      {sketch.blocks.map((b, i) => (
        <Block key={i} b={b} />
      ))}
      <rect x={1} y={1} width={width - 2} height={840} fill="none" className="stroke-base-content/25" strokeWidth={2} />
    </svg>
  );
}

function Block({ b }: { b: SketchBlock }) {
  switch (b.k) {
    case 'img':
      return (
        <g>
          <rect x={b.x} y={b.y} width={b.w} height={b.h} className="fill-base-content/20" />
          <line x1={b.x} y1={b.y} x2={b.x + b.w} y2={b.y + b.h} className="stroke-base-100/60" strokeWidth={3} />
          <line x1={b.x + b.w} y1={b.y} x2={b.x} y2={b.y + b.h} className="stroke-base-100/60" strokeWidth={3} />
        </g>
      );
    case 'txt': {
      const lines: React.ReactElement[] = [];
      for (let y = b.y; y + 7 <= b.y + b.h; y += 18) {
        const isLast = y + 25 > b.y + b.h;
        lines.push(
          <rect key={y} x={b.x} y={y} width={isLast ? b.w * 0.6 : b.w} height={7} className="fill-base-content/25" />,
        );
      }
      return <g>{lines}</g>;
    }
    case 'hl': {
      const barH = Math.min(52, Math.max(26, b.h / 3));
      const bars: React.ReactElement[] = [];
      let y = b.y;
      while (y + barH <= b.y + b.h + 1) {
        bars.push(
          <rect key={y} x={b.x} y={y} width={y === b.y ? b.w : b.w * 0.72} height={barH} className="fill-base-content/60" />,
        );
        y += barH + 14;
      }
      return <g>{bars}</g>;
    }
    case 'q':
      return (
        <g>
          <text x={b.x} y={b.y + 44} fontSize={64} className="fill-primary" fontFamily="Georgia, serif">
            &ldquo;
          </text>
          <rect x={b.x + 8} y={b.y + 58} width={b.w - 16} height={16} className="fill-primary/70" />
          <rect x={b.x + 8} y={b.y + 86} width={(b.w - 16) * 0.8} height={16} className="fill-primary/70" />
          {b.h > 120 && <rect x={b.x + 8} y={b.y + 114} width={(b.w - 16) * 0.55} height={16} className="fill-primary/70" />}
        </g>
      );
    case 'tbl': {
      const rows = 5;
      const cols = 3;
      const cells: React.ReactElement[] = [];
      for (let r = 0; r <= rows; r++) {
        cells.push(
          <line key={`r${r}`} x1={b.x} y1={b.y + (b.h / rows) * r} x2={b.x + b.w} y2={b.y + (b.h / rows) * r} className="stroke-base-content/35" strokeWidth={2} />,
        );
      }
      for (let c = 0; c <= cols; c++) {
        cells.push(
          <line key={`c${c}`} x1={b.x + (b.w / cols) * c} y1={b.y} x2={b.x + (b.w / cols) * c} y2={b.y + b.h} className="stroke-base-content/35" strokeWidth={2} />,
        );
      }
      return <g>{cells}</g>;
    }
    case 'rule':
      return <rect x={b.x} y={b.y} width={b.w} height={Math.max(3, b.h)} className="fill-base-content/50" />;
    case 'vrule':
      return <rect x={b.x} y={b.y} width={Math.max(3, b.w)} height={b.h} className="fill-base-content/40" />;
    case 'box':
      return <rect x={b.x} y={b.y} width={b.w} height={b.h} className="fill-base-content/8 stroke-base-content/30" strokeWidth={2} />;
    case 'cap':
      return <rect x={b.x} y={b.y} width={b.w} height={Math.min(10, b.h)} className="fill-base-content/35" />;
    default:
      return null;
  }
}
