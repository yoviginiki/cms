import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const MapEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { lat: number; lng: number; zoom: number; markerLabel: string; height: string };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Latitude</label>
          <input type="number" step="any" className="input input-bordered input-sm w-full" value={data.lat} onChange={(e) => update('lat', parseFloat(e.target.value) || 0)} />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Longitude</label>
          <input type="number" step="any" className="input input-bordered input-sm w-full" value={data.lng} onChange={(e) => update('lng', parseFloat(e.target.value) || 0)} />
        </div>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Zoom ({data.zoom})</label>
        <input type="range" min={1} max={20} className="range range-sm w-full" value={data.zoom} onChange={(e) => update('zoom', Number(e.target.value))} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Marker Label</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.markerLabel || ''} onChange={(e) => update('markerLabel', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Height</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.height || '400px'} onChange={(e) => update('height', e.target.value)} placeholder="e.g. 400px" />
      </div>
    </div>
  );
};
