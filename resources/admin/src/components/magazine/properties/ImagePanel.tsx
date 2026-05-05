
import type { ImageFrameData } from '@/types/magazine';
import { AssetField } from '@/components/ui/AssetPicker';

interface ImagePanelProps {
  data: ImageFrameData;
  onChange: (v: Partial<ImageFrameData>) => void;
  autoOpen?: boolean;
  onAutoOpenDone?: () => void;
}

export default function ImagePanel({ data, onChange, autoOpen, onAutoOpenDone }: ImagePanelProps) {
  // Get current URL from either src field or assetId
  const currentUrl = (data as any).src || (data.assetId ? `/api/v1/assets/${data.assetId}/serve` : '');

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Image</h3>

      {/* Asset picker */}
      <AssetField
        label="Image"
        value={currentUrl}
        onChange={(url, assetId) => onChange({ assetId: assetId || null, ...(({ src: url } as any)) })}
        accept="image"
        autoOpen={autoOpen}
        onAutoOpenDone={onAutoOpenDone}
      />

      {/* Alt text */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Alt text</label>
        <input
          type="text"
          value={data.alt}
          onChange={(e) => onChange({ alt: e.target.value })}
          className="input input-bordered input-xs w-full"
        />
      </div>

      {/* Fit */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Fit</label>
        <select
          value={data.fit}
          onChange={(e) => onChange({ fit: e.target.value as ImageFrameData['fit'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="fill">Fill</option>
          <option value="fit">Fit</option>
          <option value="stretch">Stretch</option>
          <option value="none">None</option>
        </select>
      </div>

      {/* Focal point */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Focal X</label>
          <input
            type="number"
            min={0}
            max={1}
            step={0.01}
            value={data.focalPoint.x}
            onChange={(e) => onChange({ focalPoint: { ...data.focalPoint, x: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Focal Y</label>
          <input
            type="number"
            min={0}
            max={1}
            step={0.01}
            value={data.focalPoint.y}
            onChange={(e) => onChange({ focalPoint: { ...data.focalPoint, y: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Image offset */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Offset X</label>
          <input
            type="number"
            value={data.imageOffsetX}
            onChange={(e) => onChange({ imageOffsetX: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Offset Y</label>
          <input
            type="number"
            value={data.imageOffsetY}
            onChange={(e) => onChange({ imageOffsetY: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Image scale */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Scale</label>
        <input
          type="range"
          min={0.1}
          max={4}
          step={0.1}
          value={data.imageScale}
          onChange={(e) => onChange({ imageScale: Number(e.target.value) })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{data.imageScale.toFixed(1)}x</span>
      </div>

      {/* Image rotation */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Rotation</label>
        <input
          type="number"
          min={0}
          max={360}
          value={data.imageRotation}
          onChange={(e) => onChange({ imageRotation: Number(e.target.value) })}
          className="input input-bordered input-xs w-full"
        />
      </div>

      {/* Filters */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Filters</h3>

      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Brightness</label>
        <input
          type="range"
          min={0}
          max={200}
          value={data.filters.brightness}
          onChange={(e) => onChange({ filters: { ...data.filters, brightness: Number(e.target.value) } })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{data.filters.brightness}%</span>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Contrast</label>
        <input
          type="range"
          min={0}
          max={200}
          value={data.filters.contrast}
          onChange={(e) => onChange({ filters: { ...data.filters, contrast: Number(e.target.value) } })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{data.filters.contrast}%</span>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Saturation</label>
        <input
          type="range"
          min={0}
          max={200}
          value={data.filters.saturation}
          onChange={(e) => onChange({ filters: { ...data.filters, saturation: Number(e.target.value) } })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{data.filters.saturation}%</span>
      </div>

      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={data.filters.grayscale}
          onChange={(e) => onChange({ filters: { ...data.filters, grayscale: e.target.checked } })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Grayscale</span>
      </label>
    </div>
  );
}
