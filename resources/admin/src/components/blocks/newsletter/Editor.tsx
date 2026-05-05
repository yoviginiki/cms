import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const NewsletterEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { heading: string; description: string; buttonText: string; endpoint: string; style: string };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Heading</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.heading || ''} onChange={(e) => update('heading', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Description</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.description || ''} onChange={(e) => update('description', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Button Text</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.buttonText || ''} onChange={(e) => update('buttonText', e.target.value)} />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Endpoint URL</label>
        <input type="text" className="input input-bordered input-sm w-full" value={data.endpoint || ''} onChange={(e) => update('endpoint', e.target.value)} placeholder="https://..." />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select className="select select-bordered select-sm w-full" value={data.style || 'inline'} onChange={(e) => update('style', e.target.value)}>
          <option value="inline">Inline</option>
          <option value="card">Card</option>
          <option value="full-width">Full Width</option>
        </select>
      </div>
    </div>
  );
};
