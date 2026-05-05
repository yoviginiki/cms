import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';

export const HeroEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <div>
        <label className="block text-[11px] font-medium text-base-content/50 mb-1">Title</label>
        <input type="text" value={(data.title as string) || ''} onChange={e => update('title', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="block text-[11px] font-medium text-base-content/50 mb-1">Subtitle</label>
        <input type="text" value={(data.subtitle as string) || ''} onChange={e => update('subtitle', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="block text-[11px] font-medium text-base-content/50 mb-1">CTA Text</label>
        <input type="text" value={(data.ctaText as string) || ''} onChange={e => update('ctaText', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="block text-[11px] font-medium text-base-content/50 mb-1">CTA URL</label>
        <input type="text" value={(data.ctaUrl as string) || ''} onChange={e => update('ctaUrl', e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>
    </div>
  );
};
