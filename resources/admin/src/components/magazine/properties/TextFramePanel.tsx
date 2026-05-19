
import { Link, Unlink } from 'lucide-react';
import type { TextFrameData } from '@/types/magazine';

interface TextFramePanelProps {
  data: TextFrameData;
  onChange: (v: Partial<TextFrameData>) => void;
  threadInfo?: { position: number; total: number };
  threadId?: string | null;
  onStartThread?: () => void;
  onContinueThread?: () => void;
  onUnthread?: () => void;
  availableThreadId?: string | null;  // threadId from another selected/last-started thread
}

const VALIGN_OPTIONS: { value: TextFrameData['verticalAlign']; label: string }[] = [
  { value: 'top', label: 'Top' },
  { value: 'center', label: 'Center' },
  { value: 'bottom', label: 'Bottom' },
];

export default function TextFramePanel({ data, onChange, threadInfo, threadId, onStartThread, onContinueThread, onUnthread, availableThreadId }: TextFramePanelProps) {
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Text Frame</h3>

      {/* Overflow */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Overflow</label>
        <select
          value={data.overflow}
          onChange={(e) => onChange({ overflow: e.target.value as TextFrameData['overflow'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="visible">Visible</option>
          <option value="hidden">Hidden</option>
          <option value="threaded">Threaded</option>
        </select>
      </div>

      {/* Auto-size */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Auto-size</label>
        <select
          value={data.autoSize}
          onChange={(e) => onChange({ autoSize: e.target.value as TextFrameData['autoSize'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="none">None</option>
          <option value="grow-height">Grow height</option>
          <option value="shrink-text">Shrink text</option>
        </select>
      </div>

      {/* Columns */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Columns</label>
          <input
            type="number"
            min={1}
            max={4}
            value={data.columnsInFrame}
            onChange={(e) => onChange({ columnsInFrame: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Column gap</label>
          <input
            type="number"
            value={data.columnGap}
            onChange={(e) => onChange({ columnGap: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Column fill mode */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Column fill</label>
        <div className="flex gap-1">
          <button type="button" onClick={() => onChange({ columnFill: 'auto' })}
            className={`btn btn-xs flex-1 ${data.columnFill !== 'balance' ? 'btn-primary' : 'btn-ghost'}`}>
            Auto
          </button>
          <button type="button" onClick={() => onChange({ columnFill: 'balance' })}
            className={`btn btn-xs flex-1 ${data.columnFill === 'balance' ? 'btn-primary' : 'btn-ghost'}`}>
            Balance
          </button>
        </div>
        <p className="text-[9px] text-base-content/30 mt-0.5">Auto fills first column fully. Balance splits text evenly.</p>
      </div>

      {/* Column rule */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={data.columnRule}
          onChange={(e) => onChange({ columnRule: e.target.checked })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Column rule</span>
      </label>

      {/* Text inset */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text inset</label>
        <div className="grid grid-cols-4 gap-1">
          {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
            <div key={side}>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase()}</label>
              <input
                type="number"
                value={data.textInset[side]}
                onChange={(e) => onChange({ textInset: { ...data.textInset, [side]: Number(e.target.value) } })}
                className="input input-bordered input-xs w-full"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Vertical align */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Vertical align</label>
        <div className="flex gap-1">
          {VALIGN_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => onChange({ verticalAlign: opt.value })}
              className={`btn btn-xs flex-1 ${data.verticalAlign === opt.value ? 'btn-primary' : 'btn-ghost'}`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {/* Text Threading */}
      <div className="border-t border-base-300 pt-2">
        <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Text Threading</h3>

        {threadId ? (
          <div className="space-y-2">
            <div className="flex items-center gap-2 bg-primary/10 rounded p-2">
              <Link size={12} className="text-primary shrink-0" />
              <div>
                <p className="text-[10px] text-base-content/70 font-medium">Threaded</p>
                {threadInfo && (
                  <p className="text-[9px] text-base-content/40">Frame {threadInfo.position} of {threadInfo.total}</p>
                )}
                <p className="text-[8px] text-base-content/30 font-mono">{threadId?.slice(0, 8)}...</p>
              </div>
            </div>
            {onUnthread && (
              <button type="button" onClick={onUnthread}
                className="btn btn-xs btn-ghost btn-outline w-full gap-1">
                <Unlink size={10} /> Remove from thread
              </button>
            )}
          </div>
        ) : (
          <div className="space-y-1.5">
            <p className="text-[9px] text-base-content/40">
              Link text frames so content flows from one to the next across pages.
            </p>
            {onStartThread && (
              <button type="button" onClick={onStartThread}
                className="btn btn-xs btn-primary w-full gap-1">
                <Link size={10} /> Start new thread
              </button>
            )}
            {availableThreadId && onContinueThread && (
              <button type="button" onClick={onContinueThread}
                className="btn btn-xs btn-secondary w-full gap-1">
                <Link size={10} /> Continue thread ({availableThreadId.slice(0, 8)}...)
              </button>
            )}
            {!availableThreadId && (
              <p className="text-[8px] text-base-content/30 italic">
                Start a thread on one frame, then continue it on another.
              </p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
