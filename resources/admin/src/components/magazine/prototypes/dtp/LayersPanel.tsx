/**
 * M6 DTP Canvas Prototype — Layers Panel
 *
 * Frame list with visibility, lock, selection sync, and z-order controls.
 * Prototype-only — no persistence, no API.
 */
import {
  Eye, EyeOff, Lock, Unlock, ChevronUp, ChevronDown,
  ChevronsUp, ChevronsDown, FileText, ImageIcon, Quote, Hash,
} from 'lucide-react';
import type { DtpFrame, DtpSpread } from './mockDocument';

interface Props {
  spread: DtpSpread;
  selectedIds: string[];
  onSelectFrame: (id: string) => void;
  onUpdateFrame: (id: string, updates: Partial<DtpFrame>) => void;
}

const TYPE_ICONS: Record<DtpFrame['type'], typeof FileText> = {
  text: FileText, image: ImageIcon, quote: Quote, pageNumber: Hash,
};

const TYPE_COLORS: Record<DtpFrame['type'], string> = {
  text: 'text-blue-400', image: 'text-green-400', quote: 'text-purple-400', pageNumber: 'text-amber-400',
};

export function LayersPanel({ spread, selectedIds, onSelectFrame, onUpdateFrame }: Props) {
  // Sort by zIndex descending (top layer first)
  const sorted = [...spread.frames].sort((a, b) => b.zIndex - a.zIndex);

  const moveZIndex = (id: string, direction: 'up' | 'down' | 'top' | 'bottom') => {
    // Normalize all z-indexes to unique sequential values first
    const byZ = [...spread.frames].sort((a, b) => a.zIndex - b.zIndex || a.id.localeCompare(b.id));
    // Assign sequential z-indexes to eliminate duplicates
    byZ.forEach((f, i) => { if (f.zIndex !== i) onUpdateFrame(f.id, { zIndex: i }); });

    const idx = byZ.findIndex(f => f.id === id);
    if (idx < 0) return;

    if (direction === 'top') {
      const reordered = [...byZ.filter(f => f.id !== id), byZ[idx]];
      reordered.forEach((f, i) => onUpdateFrame(f.id, { zIndex: i }));
    } else if (direction === 'bottom') {
      const reordered = [byZ[idx], ...byZ.filter(f => f.id !== id)];
      reordered.forEach((f, i) => onUpdateFrame(f.id, { zIndex: i }));
    } else if (direction === 'up' && idx < byZ.length - 1) {
      onUpdateFrame(id, { zIndex: idx + 1 });
      onUpdateFrame(byZ[idx + 1].id, { zIndex: idx });
    } else if (direction === 'down' && idx > 0) {
      onUpdateFrame(id, { zIndex: idx - 1 });
      onUpdateFrame(byZ[idx - 1].id, { zIndex: idx });
    }
  };

  return (
    <div className="p-2 space-y-1">
      <div className="flex items-center justify-between px-1 mb-2">
        <h3 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider">Layers</h3>
        <span className="text-[9px] text-neutral-500">{spread.frames.length} frames</span>
      </div>

      {sorted.map(frame => {
        const isSelected = selectedIds.includes(frame.id);
        const isVisible = frame.visible !== false;
        const isLocked = frame.locked === true;
        const Icon = TYPE_ICONS[frame.type];
        const colorClass = TYPE_COLORS[frame.type];

        return (
          <div
            key={frame.id}
            className={`flex items-center gap-1 px-1.5 py-1 rounded cursor-pointer transition-colors ${
              isSelected ? 'bg-blue-600/20 border border-blue-500/30' : 'hover:bg-neutral-700/50 border border-transparent'
            } ${!isVisible ? 'opacity-40' : ''}`}
            onClick={() => onSelectFrame(frame.id)}
          >
            {/* Visibility toggle */}
            <button
              onClick={(e) => { e.stopPropagation(); onUpdateFrame(frame.id, { visible: !isVisible }); }}
              className="p-0.5 rounded hover:bg-neutral-600/50 shrink-0"
              title={isVisible ? 'Hide' : 'Show'}
            >
              {isVisible ? <Eye size={11} className="text-neutral-400" /> : <EyeOff size={11} className="text-neutral-500" />}
            </button>

            {/* Lock toggle */}
            <button
              onClick={(e) => { e.stopPropagation(); onUpdateFrame(frame.id, { locked: !isLocked }); }}
              className="p-0.5 rounded hover:bg-neutral-600/50 shrink-0"
              title={isLocked ? 'Unlock' : 'Lock'}
            >
              {isLocked ? <Lock size={11} className="text-amber-400" /> : <Unlock size={11} className="text-neutral-500" />}
            </button>

            {/* Type icon + name */}
            <Icon size={11} className={`${colorClass} shrink-0`} />
            <span className={`text-[10px] flex-1 truncate ${isSelected ? 'text-neutral-200 font-medium' : 'text-neutral-300'}`}>
              {frame.label || frame.type}
            </span>

            {/* Z-index */}
            <span className="text-[8px] text-neutral-500 font-mono shrink-0">z{frame.zIndex}</span>

            {/* Reorder buttons (visible on hover/selection) */}
            {isSelected && (
              <div className="flex gap-0.5 shrink-0">
                <button onClick={(e) => { e.stopPropagation(); moveZIndex(frame.id, 'top'); }}
                  className="p-0.5 rounded hover:bg-neutral-600" title="Bring to front">
                  <ChevronsUp size={10} className="text-neutral-400" />
                </button>
                <button onClick={(e) => { e.stopPropagation(); moveZIndex(frame.id, 'up'); }}
                  className="p-0.5 rounded hover:bg-neutral-600" title="Move up">
                  <ChevronUp size={10} className="text-neutral-400" />
                </button>
                <button onClick={(e) => { e.stopPropagation(); moveZIndex(frame.id, 'down'); }}
                  className="p-0.5 rounded hover:bg-neutral-600" title="Move down">
                  <ChevronDown size={10} className="text-neutral-400" />
                </button>
                <button onClick={(e) => { e.stopPropagation(); moveZIndex(frame.id, 'bottom'); }}
                  className="p-0.5 rounded hover:bg-neutral-600" title="Send to back">
                  <ChevronsDown size={10} className="text-neutral-400" />
                </button>
              </div>
            )}
          </div>
        );
      })}

      {spread.frames.length === 0 && (
        <p className="text-[10px] text-neutral-500 text-center py-4">No frames on this spread</p>
      )}
    </div>
  );
}
