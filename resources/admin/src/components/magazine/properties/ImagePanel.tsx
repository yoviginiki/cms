
import { useState } from 'react';
import { Trash2, RotateCcw } from 'lucide-react';
import type { ImageFrameData } from '@/types/magazine';
import { AssetField } from '@/components/ui/AssetPicker';

interface ImagePanelProps {
  data: ImageFrameData;
  onChange: (v: Partial<ImageFrameData>) => void;
  autoOpen?: boolean;
  onAutoOpenDone?: () => void;
}

const SHADOW_PRESETS = [
  { label: 'None', value: null },
  { label: 'Subtle', value: '0 1px 3px rgba(0,0,0,0.12)' },
  { label: 'Medium', value: '0 4px 12px rgba(0,0,0,0.15)' },
  { label: 'Strong', value: '0 8px 24px rgba(0,0,0,0.2)' },
  { label: 'Float', value: '0 12px 40px rgba(0,0,0,0.25)' },
];

export default function ImagePanel({ data, onChange, autoOpen, onAutoOpenDone }: ImagePanelProps) {
  const currentUrl = (data as any).src || (data.assetId ? `/api/v1/assets/${data.assetId}/serve` : '');
  const hasImage = !!(currentUrl || data.assetId);
  const [showAdvanced, setShowAdvanced] = useState(false);

  // Focal point as 0-100 for UX, stored as 0-1
  const focalX = Math.round((data.focalPoint?.x ?? 0.5) * 100);
  const focalY = Math.round((data.focalPoint?.y ?? 0.5) * 100);

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Image</h3>

      {/* Asset picker */}
      <AssetField
        label="Image"
        value={currentUrl}
        onChange={(url, assetId) => onChange({ assetId: assetId || null, ...({ src: url } as any) })}
        accept="image"
        autoOpen={autoOpen}
        onAutoOpenDone={onAutoOpenDone}
      />

      {/* Clear image */}
      {hasImage && (
        <button
          type="button"
          onClick={() => onChange({ assetId: null, ...({ src: '' } as any) })}
          className="btn btn-xs btn-ghost btn-outline w-full gap-1 text-error/60 hover:text-error"
        >
          <Trash2 size={10} /> Clear image
        </button>
      )}

      {/* Alt text */}
      <div>
        <label htmlFor="imagepanel-alt-text-1" className="text-[10px] text-base-content/40 mb-0.5 block">Alt text</label>
        <input id="imagepanel-alt-text-1"
          type="text"
          value={data.alt || ''}
          onChange={(e) => onChange({ alt: e.target.value })}
          className="input input-bordered input-xs w-full"
          placeholder="Describe the image..."
        />
        {!data.alt && hasImage && (
          <p className="text-[8px] text-warning/60 mt-0.5">Missing alt text — required for accessibility</p>
        )}
      </div>

      {/* Caption */}
      <div>
        <label htmlFor="imagepanel-caption-2" className="text-[10px] text-base-content/40 mb-0.5 block">Caption</label>
        <input id="imagepanel-caption-2"
          type="text"
          value={(data as any).caption || ''}
          onChange={(e) => onChange({ ...({ caption: e.target.value } as any) })}
          className="input input-bordered input-xs w-full"
          placeholder="Photo credit or description..."
        />
      </div>

      {/* Show caption toggle */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input name="mag-imagepanel-1"
          type="checkbox"
          checked={(data as any).showCaption ?? true}
          onChange={(e) => onChange({ ...({ showCaption: e.target.checked } as any) })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Show caption below image</span>
      </label>

      {/* Fit mode */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Fit mode</label>
        <div className="flex gap-1">
          {(['fill', 'fit', 'stretch', 'none'] as const).map(mode => (
            <button
              key={mode}
              type="button"
              onClick={() => onChange({ fit: mode })}
              className={`btn btn-xs flex-1 ${data.fit === mode ? 'btn-primary' : 'btn-ghost'}`}
            >
              {mode === 'none' ? 'Original' : mode.charAt(0).toUpperCase() + mode.slice(1)}
            </button>
          ))}
        </div>
        <p className="text-[8px] text-base-content/25 mt-0.5">
          {data.fit === 'fill' && 'Cover — fills frame, crops excess'}
          {data.fit === 'fit' && 'Contain — fits inside frame, may letterbox'}
          {data.fit === 'stretch' && 'Stretch — distorts to fill frame'}
          {data.fit === 'none' && 'Original — natural size, may overflow'}
        </p>
      </div>

      {/* Focal point — 0-100% */}
      <div>
        <div className="flex items-center justify-between mb-0.5">
          <label className="text-[10px] text-base-content/40">Focal point</label>
          <button
            type="button"
            onClick={() => onChange({ focalPoint: { x: 0.5, y: 0.5 } })}
            className="text-[8px] text-base-content/30 hover:text-base-content/60 flex items-center gap-0.5"
            title="Reset to center"
          >
            <RotateCcw size={8} /> Reset
          </button>
        </div>
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="text-[8px] text-base-content/30 block">X: {focalX}%</label>
            <input name="mag-imagepanel-2"
              type="range"
              min={0}
              max={100}
              value={focalX}
              onChange={(e) => onChange({ focalPoint: { ...data.focalPoint, x: Number(e.target.value) / 100 } })}
              className="range range-xs w-full"
            />
          </div>
          <div>
            <label className="text-[8px] text-base-content/30 block">Y: {focalY}%</label>
            <input name="mag-imagepanel-3"
              type="range"
              min={0}
              max={100}
              value={focalY}
              onChange={(e) => onChange({ focalPoint: { ...data.focalPoint, y: Number(e.target.value) / 100 } })}
              className="range range-xs w-full"
            />
          </div>
        </div>
      </div>

      {/* Opacity */}
      <div>
        <label htmlFor="imagepanel-opacity-3" className="text-[10px] text-base-content/40 mb-0.5 block">Opacity</label>
        <input id="imagepanel-opacity-3"
          type="range"
          min={0}
          max={100}
          value={Math.round(((data as any).opacity ?? 100))}
          onChange={(e) => onChange({ ...({ opacity: Number(e.target.value) } as any) })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{Math.round((data as any).opacity ?? 100)}%</span>
      </div>

      {/* Image scale */}
      <div>
        <label htmlFor="imagepanel-scale-4" className="text-[10px] text-base-content/40 mb-0.5 block">Scale</label>
        <input id="imagepanel-scale-4"
          type="range"
          min={10}
          max={400}
          step={10}
          value={Math.round((data.imageScale ?? 1) * 100)}
          onChange={(e) => onChange({ imageScale: Number(e.target.value) / 100 })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{Math.round((data.imageScale ?? 1) * 100)}%</span>
      </div>

      {/* Shadow presets */}
      <div>
        <label htmlFor="imagepanel-shadow-preset-5" className="text-[10px] text-base-content/40 mb-0.5 block">Shadow preset</label>
        <select id="imagepanel-shadow-preset-5"
          value={(data as any).shadowPreset || 'none'}
          onChange={(e) => {
            const preset = SHADOW_PRESETS.find(p => p.label.toLowerCase() === e.target.value);
            onChange({ ...({ shadowPreset: e.target.value, shadowCss: preset?.value || null } as any) });
          }}
          className="select select-bordered select-xs w-full"
        >
          {SHADOW_PRESETS.map(p => (
            <option key={p.label} value={p.label.toLowerCase()}>{p.label}</option>
          ))}
        </select>
      </div>

      {/* Border radius */}
      <div>
        <label htmlFor="imagepanel-border-radius-6" className="text-[10px] text-base-content/40 mb-0.5 block">Border radius</label>
        <input id="imagepanel-border-radius-6"
          type="range"
          min={0}
          max={50}
          value={(data as any).borderRadius ?? 0}
          onChange={(e) => onChange({ ...({ borderRadius: Number(e.target.value) } as any) })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{(data as any).borderRadius ?? 0}px</span>
      </div>

      {/* Background color (visible when fit=contain/original) */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Background</label>
        <div className="flex gap-1">
          <input name="mag-imagepanel-4"
            type="color"
            value={(data as any).backgroundColor || '#ffffff'}
            onChange={(e) => onChange({ ...({ backgroundColor: e.target.value } as any) })}
            className="w-8 h-6 cursor-pointer rounded border border-base-300"
          />
          <input name="mag-imagepanel-5"
            type="text"
            value={(data as any).backgroundColor || '#ffffff'}
            onChange={(e) => onChange({ ...({ backgroundColor: e.target.value } as any) })}
            className="input input-bordered input-xs flex-1"
          />
        </div>
      </div>

      {/* Advanced section */}
      <div className="border-t border-base-300 pt-2">
        <button
          type="button"
          onClick={() => setShowAdvanced(!showAdvanced)}
          className="text-[10px] text-base-content/40 hover:text-base-content/60 w-full text-left"
        >
          {showAdvanced ? '− Advanced' : '+ Advanced'}
        </button>

        {showAdvanced && (
          <div className="space-y-3 mt-2">
            {/* Image offset */}
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label htmlFor="imagepanel-offset-x-7" className="text-[10px] text-base-content/40 mb-0.5 block">Offset X</label>
                <input id="imagepanel-offset-x-7" type="number" value={data.imageOffsetX || 0} onChange={(e) => onChange({ imageOffsetX: Number(e.target.value) })} className="input input-bordered input-xs w-full" />
              </div>
              <div>
                <label htmlFor="imagepanel-offset-y-8" className="text-[10px] text-base-content/40 mb-0.5 block">Offset Y</label>
                <input id="imagepanel-offset-y-8" type="number" value={data.imageOffsetY || 0} onChange={(e) => onChange({ imageOffsetY: Number(e.target.value) })} className="input input-bordered input-xs w-full" />
              </div>
            </div>

            {/* Image rotation */}
            <div>
              <label htmlFor="imagepanel-rotation-9" className="text-[10px] text-base-content/40 mb-0.5 block">Rotation</label>
              <input id="imagepanel-rotation-9" type="number" min={0} max={360} value={data.imageRotation || 0} onChange={(e) => onChange({ imageRotation: Number(e.target.value) })} className="input input-bordered input-xs w-full" />
            </div>

            {/* Clip shape */}
            <div>
              <label htmlFor="imagepanel-clip-shape-10" className="text-[10px] text-base-content/40 mb-0.5 block">Clip shape</label>
              <select id="imagepanel-clip-shape-10" value={data.clipShape || 'rectangle'} onChange={(e) => onChange({ clipShape: e.target.value as ImageFrameData['clipShape'] })} className="select select-bordered select-xs w-full">
                <option value="rectangle">Rectangle</option>
                <option value="ellipse">Ellipse</option>
                <option value="polygon">Polygon</option>
              </select>
            </div>

            {/* Filters */}
            <h4 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Filters</h4>
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Brightness ({data.filters?.brightness ?? 100}%)</label>
              <input name="mag-imagepanel-6" type="range" min={0} max={200} value={data.filters?.brightness ?? 100} onChange={(e) => onChange({ filters: { ...data.filters, brightness: Number(e.target.value) } })} className="range range-xs w-full" />
            </div>
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Contrast ({data.filters?.contrast ?? 100}%)</label>
              <input name="mag-imagepanel-7" type="range" min={0} max={200} value={data.filters?.contrast ?? 100} onChange={(e) => onChange({ filters: { ...data.filters, contrast: Number(e.target.value) } })} className="range range-xs w-full" />
            </div>
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Saturation ({data.filters?.saturation ?? 100}%)</label>
              <input name="mag-imagepanel-8" type="range" min={0} max={200} value={data.filters?.saturation ?? 100} onChange={(e) => onChange({ filters: { ...data.filters, saturation: Number(e.target.value) } })} className="range range-xs w-full" />
            </div>
            <label className="flex items-center gap-1.5 cursor-pointer">
              <input name="mag-imagepanel-9" type="checkbox" checked={data.filters?.grayscale ?? false} onChange={(e) => onChange({ filters: { ...data.filters, grayscale: e.target.checked } })} className="checkbox checkbox-xs" />
              <span className="text-[10px] text-base-content/40">Grayscale</span>
            </label>
          </div>
        )}
      </div>
    </div>
  );
}
