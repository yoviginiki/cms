import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';

export const CtabannerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Heading</label>
        <input type="text" className="input input-bordered input-sm w-full"
          value={(data.heading as string) || ''} onChange={e => update('heading', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Text</label>
        <textarea className="textarea textarea-bordered textarea-sm w-full" rows={2}
          value={(data.text as string) || ''} onChange={e => update('text', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Button Text</label>
        <input type="text" className="input input-bordered input-sm w-full"
          value={(data.buttonText as string) || ''} onChange={e => update('buttonText', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Button URL</label>
        <input type="text" className="input input-bordered input-sm w-full"
          value={(data.buttonUrl as string) || ''} onChange={e => update('buttonUrl', e.target.value)} placeholder="https://..." />
      </div>
    </div>
  );
};
