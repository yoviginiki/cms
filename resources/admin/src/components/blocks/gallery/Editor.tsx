import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const GalleryEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { images, layout, columns, gap } = block.data as {
    images: string[];
    layout: string;
    columns: number;
    gap: string;
  };

  const imgList = Array.isArray(images) ? images : [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Images (one URL per line)</label>
        <textarea
          value={imgList.join('\n')}
          onChange={(e) =>
            update(
              'images',
              e.target.value
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
            )
          }
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
          rows={5}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Layout</label>
        <select
          value={layout || 'grid'}
          onChange={(e) => update('layout', e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="grid">Grid</option>
          <option value="masonry">Masonry</option>
          <option value="carousel">Carousel</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <input
          type="number"
          min={2}
          max={6}
          value={columns || 3}
          onChange={(e) => update('columns', parseInt(e.target.value, 10))}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Gap</label>
        <input
          type="text"
          value={gap || '8px'}
          onChange={(e) => update('gap', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      </div>
    </div>
  );
};
