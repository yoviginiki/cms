import React from 'react';
import { Link, useParams } from 'react-router-dom';
import type { BlockEditorProps } from '@/types/blocks';

export const SiteIdentityEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { siteId = '' } = useParams();
  const d = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-4">
      <p className="text-[11px] text-base-content/50 leading-relaxed">
        Logo, name and tagline come from <Link to={`/sites/${siteId}/settings`} className="text-primary underline">Site Settings → Branding</Link> — change them there once and every page follows.
      </p>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Show</label>
        <select value={(d.show as string) || 'auto'} onChange={(e) => update('show', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="auto">Automatic (logo if set, else name)</option>
          <option value="logo">Logo</option>
          <option value="name">Site name</option>
          <option value="both">Logo + name</option>
        </select>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
        <select value={(d.size as string) || 'md'} onChange={(e) => update('size', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option>
        </select>
      </div>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" className="checkbox checkbox-xs" checked={!!d.showTagline} onChange={(e) => update('showTagline', e.target.checked)} /> Show tagline
      </label>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" className="checkbox checkbox-xs" checked={d.linkHome !== false} onChange={(e) => update('linkHome', e.target.checked)} /> Link to the homepage
      </label>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
        <select value={(d.align as string) || 'left'} onChange={(e) => update('align', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="left">Left</option><option value="center">Center</option><option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
