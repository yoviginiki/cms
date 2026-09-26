import React from 'react';
import { Plus, Trash2, ChevronUp } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { SOCIAL_NETWORKS } from './definition';

type Link = { network: string; url: string; label?: string; icon?: string };

const PLACEHOLDER: Record<string, string> = {
  contact: 'Email, phone or a page (/kontakti/)', email: 'name@example.com', phone: '+359 88 123 4567', website: 'example.com', whatsapp: 'https://wa.me/359881234567',
};

export const SocialLinksEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const d = block.data as Record<string, unknown>;
  const links = (d.links as Link[]) || [];
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const setLinks = (next: Link[]) => update('links', next);
  const setLink = (i: number, patch: Partial<Link>) => setLinks(links.map((l, j) => (j === i ? { ...l, ...patch } : l)));

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <label className="text-[11px] text-base-content/50 block">Links</label>
        {links.map((l, i) => (
          <div key={i} className="rounded border border-base-300 p-2 space-y-1.5">
            <div className="flex gap-1.5">
              <select value={l.network} onChange={(e) => setLink(i, { network: e.target.value })} className="select select-bordered select-xs flex-1 text-[12px]">
                {Object.entries(SOCIAL_NETWORKS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
              <button type="button" disabled={i === 0} title="Move up" className="btn btn-ghost btn-xs btn-square"
                onClick={() => { const n = [...links]; [n[i - 1], n[i]] = [n[i], n[i - 1]]; setLinks(n); }}><ChevronUp size={12} /></button>
              <button type="button" title="Remove" className="btn btn-ghost btn-xs btn-square text-error" onClick={() => setLinks(links.filter((_, j) => j !== i))}><Trash2 size={12} /></button>
            </div>
            <input type="text" value={l.url || ''} onChange={(e) => setLink(i, { url: e.target.value })}
              placeholder={PLACEHOLDER[l.network] || `https://${l.network}.com/yourpage`} className="input input-bordered input-xs w-full text-[12px]" />
            <input type="text" value={l.label || ''} onChange={(e) => setLink(i, { label: e.target.value })}
              placeholder={`Label (default: ${SOCIAL_NETWORKS[l.network] || 'Link'})`} className="input input-bordered input-xs w-full text-[12px]" />
            <AssetField label="Own icon (optional — replaces the built-in one)" value={l.icon || ''} onChange={(url) => setLink(i, { icon: url })} accept="image" />
            {l.icon && (
              <button type="button" onClick={() => setLink(i, { icon: '' })} className="text-[10px] text-base-content/50 underline">Use the built-in icon again</button>
            )}
          </div>
        ))}
        <button type="button" onClick={() => setLinks([...links, { network: 'website', url: '' }])} className="btn btn-xs btn-ghost border border-base-300 w-full gap-1">
          <Plus size={12} /> Add link
        </button>
        <p className="text-[10px] text-base-content/40">Links without a URL are not published. A page of this site can be linked as /page-slug/.</p>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
          <select value={(d.style as string) || 'circle'} onChange={(e) => update('style', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
            <option value="circle">Circle</option><option value="square">Square</option><option value="icon">Icon only</option><option value="text">Text only</option>
          </select>
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
          <select value={(d.size as string) || 'md'} onChange={(e) => update('size', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
            <option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option>
          </select>
        </div>
      </div>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" className="checkbox checkbox-xs" checked={!!d.showLabels} onChange={(e) => update('showLabels', e.target.checked)} /> Show labels next to icons
      </label>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Icon color (empty = text color)</label>
        <input type="text" value={(d.color as string) || ''} onChange={(e) => update('color', e.target.value)} placeholder="#000000" className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
        <select value={(d.align as string) || 'left'} onChange={(e) => update('align', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="left">Left</option><option value="center">Center</option><option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
