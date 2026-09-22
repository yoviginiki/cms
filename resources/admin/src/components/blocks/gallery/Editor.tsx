import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField } from '@/components/editor/fields';
import { GalleryImagesField } from '@/components/editor/fields/GalleryImagesField';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';
import { normalizeGalleryImages, type GalleryAspect } from './types';

export const GalleryEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const images = normalizeGalleryImages(data.images);
  const layout = (data.layout as string) || 'grid';
  const columns = (data.columns as number) || 3;
  const gap = (data.gap as string) || '8px';
  const aspect = (data.aspect as GalleryAspect) || 'square';
  const lightbox = data.lightbox !== false;
  const captions = data.captions === true;

  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-4">
      <GalleryImagesField images={images} onChange={(imgs) => update('images', imgs)} />

      {/* ─── Layout ─── */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
          <select value={layout} onChange={(e) => update('layout', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
            <option value="grid">Grid</option>
            <option value="masonry">Masonry</option>
            <option value="carousel">Carousel</option>
          </select>
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
          <input type="number" min={1} max={8} value={columns}
            onChange={(e) => update('columns', Math.max(1, Math.min(8, parseInt(e.target.value, 10) || 1)))}
            className="input input-bordered input-sm w-full text-[12px]" />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Thumbnail shape</label>
          <select value={aspect} onChange={(e) => update('aspect', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
            <option value="square">Square</option>
            <option value="4:3">4:3</option>
            <option value="3:2">3:2</option>
            <option value="16:9">16:9</option>
            <option value="natural">Natural (no crop)</option>
          </select>
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Gap</label>
          <input type="text" value={gap} onChange={(e) => update('gap', e.target.value)} className="input input-bordered input-sm w-full text-[12px]" />
        </div>
      </div>

      <ToggleField label="Open in lightbox on click" value={lightbox} onChange={(v) => update('lightbox', v)} />
      <ToggleField label="Show captions under images" value={captions} onChange={(v) => update('captions', v)} />

      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel value={(block.data as any).effects || {}} onChange={(v: CardEffects) => update('effects', v)} />
      </div>
    </div>
  );
};
