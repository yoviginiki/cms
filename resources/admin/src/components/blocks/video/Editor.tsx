import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { ToggleField } from '@/components/editor/fields/ToggleField';
import { AssetField } from '@/components/ui/AssetPicker';

export const VideoEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    url: string; autoplay: boolean; muted: boolean; loop: boolean; poster: string;
    controls?: boolean; playsinline?: boolean; preload?: string;
    heroMode: boolean; shape: string; shapeRadius: string; minHeight: string;
    overlay: boolean; overlayColor: string; overlayOpacity: number;
    preTitle: string; title: string; subtitle: string; textColor: string;
  };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <AssetField label="Video file" value={(data.url as string) || ''} accept="video"
        onChange={(v) => update('url', v)} />
      <TextField
        label="…or Video URL"
        value={data.url || ''}
        onChange={(v) => update('url', v)}
        placeholder="https://youtube.com/watch?v=... or .mp4 URL"
      />
      <div className="grid grid-cols-3 gap-2">
        <ToggleField label="Autoplay" value={!!data.autoplay} onChange={(v) => update('autoplay', v)} />
        <ToggleField label="Muted" value={!!data.muted} onChange={(v) => update('muted', v)} />
        <ToggleField label="Loop" value={!!data.loop} onChange={(v) => update('loop', v)} />
      </div>
      <div className="grid grid-cols-3 gap-2">
        <ToggleField label="Controls" value={data.controls !== false} onChange={(v) => update('controls', v)} />
        <ToggleField label="Plays inline" value={!!data.playsinline} onChange={(v) => update('playsinline', v)} />
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Preload</label>
          <select value={(data.preload as string) || 'metadata'} onChange={(e) => update('preload', e.target.value)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="none">None</option>
            <option value="metadata">Metadata</option>
            <option value="auto">Auto</option>
          </select>
        </div>
      </div>
      <AssetField label="Poster image" value={data.poster || ''} onChange={(v) => update('poster', v)} accept="image" />

      {/* Shape applies in ALL modes (round/ellipse via 50%, capsule, custom) */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Shape</label>
          <select className="select select-bordered select-xs w-full text-[11px]" value={data.shape || 'none'} onChange={(e) => update('shape', e.target.value)}>
            <option value="none">Square (none)</option>
            <option value="rounded">Rounded (2rem)</option>
            <option value="capsule">Capsule / Pill</option>
            <option value="circle">Circle / Ellipse</option>
            <option value="custom">Custom radius</option>
          </select>
        </div>
        {data.shape === 'custom' && (
          <TextField label="Radius" value={data.shapeRadius || ''} onChange={(v) => update('shapeRadius', v)} placeholder="50% 50% 0 0" />
        )}
      </div>

      {/* Hero Mode */}
      <div className="border-t border-base-300/20 pt-3">
        <ToggleField label="Hero mode (background video)" value={!!data.heroMode} onChange={(v) => update('heroMode', v)} />
      </div>

      {data.heroMode && (
        <div className="space-y-3 pl-1 border-l-2 border-primary/20 ml-1">
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Min Height</label>
            <select className="select select-bordered select-sm w-full" value={data.minHeight || '80vh'} onChange={(e) => update('minHeight', e.target.value)}>
              <option value="50vh">50vh (half screen)</option>
              <option value="60vh">60vh</option>
              <option value="70vh">70vh</option>
              <option value="80vh">80vh</option>
              <option value="100vh">100vh (full screen)</option>
              <option value="400px">400px</option>
              <option value="600px">600px</option>
            </select>
          </div>

          {/* Shape moved to the always-visible section above */}

          <div className="border-t border-base-300/10 pt-2">
            <ToggleField label="Color overlay" value={!!data.overlay} onChange={(v) => update('overlay', v)} />
          </div>

          {data.overlay && (
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Overlay color</label>
                <div className="flex gap-1">
                  <input type="color" className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                    value={data.overlayColor || '#000000'} onChange={(e) => update('overlayColor', e.target.value)} />
                  <input type="text" className="input input-bordered input-xs flex-1 font-mono text-[10px]"
                    value={data.overlayColor || ''} onChange={(e) => update('overlayColor', e.target.value)}
                    placeholder="rgba(0,0,0,0.4)" />
                </div>
              </div>
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Opacity</label>
                <input type="range" className="range range-xs range-primary w-full" min={0} max={1} step={0.05}
                  value={data.overlayOpacity ?? 0.4} onChange={(e) => update('overlayOpacity', Number(e.target.value))} />
                <span className="text-[9px] text-base-content/30">{data.overlayOpacity ?? 0.4}</span>
              </div>
            </div>
          )}

          <div className="border-t border-base-300/10 pt-2">
            <TextField label="Pre-title / Kicker" value={data.preTitle || ''} onChange={(v) => update('preTitle', v)} placeholder="e.g. ensodo presents" />
            <div className="mt-2">
              <TextField label="Title" value={data.title || ''} onChange={(v) => update('title', v)} placeholder="Hero heading" />
            </div>
            <div className="mt-2">
              <TextField label="Subtitle" value={data.subtitle || ''} onChange={(v) => update('subtitle', v)} placeholder="Subtitle text" />
            </div>
            <div className="mt-2">
              <label className="text-[11px] text-base-content/50 mb-1 block">Text color</label>
              <div className="flex gap-1">
                <input type="color" className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                  value={data.textColor || '#ffffff'} onChange={(e) => update('textColor', e.target.value)} />
                <input type="text" className="input input-bordered input-xs flex-1 font-mono text-[10px]"
                  value={data.textColor || '#ffffff'} onChange={(e) => update('textColor', e.target.value)} />
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
