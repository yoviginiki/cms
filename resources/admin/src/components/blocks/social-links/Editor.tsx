import React, { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { customFonts as customFontsApi } from '@/lib/api';
import { Plus, Trash2, ChevronUp } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { SOCIAL_NETWORKS, socialIconPx, type SocialTitle } from './definition';

type Link = { network: string; url: string; label?: string; icon?: string };

// Fonts that are always loaded on the published site (theme tokens, site fonts, system stacks) —
// no arbitrary Google font here, it would not be self-hosted on publish.
const BASE_FONTS: [string, string][] = [
  ['', 'Inherit (same as the footer)'],
  ['var(--font-heading)', 'Theme heading font'],
  ['var(--font-body)', 'Theme body font'],
  ["Georgia, 'Times New Roman', serif", 'Serif (Georgia)'],
  ['Arial, Helvetica, sans-serif', 'Sans-serif (Arial)'],
  ["'Trebuchet MS', sans-serif", 'Trebuchet'],
  ["'Courier New', monospace", 'Monospace'],
];

const lbl = 'text-[11px] text-base-content/50 mb-1 block';
const sel = 'select select-bordered select-xs w-full text-[12px]';
const num = 'input input-bordered input-xs w-full text-[12px]';

const PLACEHOLDER: Record<string, string> = {
  contact: 'Email, phone or a page (/kontakti/)', email: 'name@example.com', phone: '+359 88 123 4567', website: 'example.com', whatsapp: 'https://wa.me/359881234567',
};

export const SocialLinksEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const d = block.data as Record<string, unknown>;
  const links = (d.links as Link[]) || [];
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const setLinks = (next: Link[]) => update('links', next);
  const setLink = (i: number, patch: Partial<Link>) => setLinks(links.map((l, j) => (j === i ? { ...l, ...patch } : l)));

  const { siteId } = useParams<{ siteId: string }>();
  const { data: siteFonts } = useQuery<any[]>({
    queryKey: ['custom-fonts', siteId],
    queryFn: () => (siteId ? customFontsApi.list(siteId).then((r: any) => r.data.data) : Promise.resolve([])),
    enabled: !!siteId,
  });
  const fonts = useMemo(() => {
    const own = Array.from(new Set((siteFonts || []).map((f: any) => f.family as string)))
      .map((f): [string, string] => [`'${f}'`, `${f} (site font)`]);
    return [...BASE_FONTS.slice(0, 3), ...own, ...BASE_FONTS.slice(3)];
  }, [siteFonts]);

  const t = (d.title || {}) as SocialTitle;
  const setTitle = (patch: Partial<SocialTitle>) => update('title', { ...t, ...patch });
  const pad = t.padding || {};
  const numOrNull = (v: string) => (v === '' ? null : Number(v));

  const iconPx = socialIconPx(d);
  const gapPx = typeof d.gap === 'number' ? d.gap : 8;
  const style = (d.style as string) || 'circle';
  const iconBgMode = !d.iconBg ? '' : d.iconBg === 'none' ? 'none' : 'custom';
  const addLink = () => setLinks([...links, { network: 'website', url: '' }]);

  return (
    <div className="space-y-4">
      <details className="rounded border border-primary/40 bg-primary/5 p-2" open>
        <summary className="text-[12px] font-semibold cursor-pointer select-none">Title {t.show && t.text ? `“${t.text}”` : '(off)'} <span className="font-normal text-base-content/50">— text, position, alignment, font, size, color</span></summary>
        <div className="space-y-2 mt-2">
          <label className="flex items-center gap-2 text-[12px]">
            <input type="checkbox" className="toggle toggle-xs" checked={!!t.show} onChange={(e) => setTitle({ show: e.target.checked, text: t.text || 'Follow Us' })} /> Show title
          </label>
          {t.show && (<>
            <input type="text" value={t.text || ''} onChange={(e) => setTitle({ text: e.target.value })} placeholder="Follow Us" className="input input-bordered input-xs w-full text-[12px]" />
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className={lbl}>Position</label>
                <select value={t.position || 'above'} onChange={(e) => setTitle({ position: e.target.value as SocialTitle['position'] })} className={sel}>
                  <option value="above">Above the icons</option><option value="below">Under the icons</option>
                  <option value="before">Before (same line)</option><option value="after">After (same line)</option>
                </select>
              </div>
              <div>
                <label className={lbl}>Tag</label>
                <select value={t.tag || 'h3'} onChange={(e) => setTitle({ tag: e.target.value })} className={sel}>
                  {['h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'].map((x) => <option key={x} value={x}>{x.toUpperCase()}</option>)}
                </select>
              </div>
            </div>
            <div>
              <label className={lbl}>Title alignment</label>
              <div className="join w-full">
                {([['', 'Auto'], ['left', 'Left'], ['center', 'Center'], ['right', 'Right']] as const).map(([v, n]) => (
                  <button key={v} type="button" onClick={() => setTitle({ align: v || undefined })}
                    className={`btn btn-xs join-item flex-1 ${(t.align || '') === v ? 'btn-primary' : 'btn-ghost border border-base-300'}`}>{n}</button>
                ))}
              </div>
              <p className="text-[10px] text-base-content/40 mt-0.5">Auto = same as the icons. For “same line” positions the whole row follows the icons alignment below.</p>
            </div>
            <div>
              <label className={lbl}>Font</label>
              <select value={t.fontFamily || ''} onChange={(e) => setTitle({ fontFamily: e.target.value })} className={sel}>
                {fonts.map(([v, n]) => <option key={v} value={v}>{n}</option>)}
              </select>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <div>
                <label className={lbl}>Size (px)</label>
                <input type="number" min={8} max={120} value={t.fontSize ?? ''} placeholder="auto" onChange={(e) => setTitle({ fontSize: numOrNull(e.target.value) })} className={num} />
              </div>
              <div>
                <label className={lbl}>Weight</label>
                <select value={t.fontWeight || ''} onChange={(e) => setTitle({ fontWeight: e.target.value })} className={sel}>
                  <option value="">Auto</option>
                  {['300', '400', '500', '600', '700', '800', '900'].map((w) => <option key={w} value={w}>{w}</option>)}
                </select>
              </div>
              <div>
                <label className={lbl}>Style</label>
                <select value={t.fontStyle || ''} onChange={(e) => setTitle({ fontStyle: e.target.value })} className={sel}>
                  <option value="">Auto</option><option value="normal">Normal</option><option value="italic">Italic</option>
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className={lbl}>Letter case</label>
                <select value={t.textTransform || ''} onChange={(e) => setTitle({ textTransform: e.target.value })} className={sel}>
                  <option value="">Auto</option><option value="none">As typed</option><option value="uppercase">UPPERCASE</option>
                  <option value="lowercase">lowercase</option><option value="capitalize">Capitalize</option>
                </select>
              </div>
              <div>
                <label className={lbl}>Letter spacing (px)</label>
                <input type="number" step={0.5} min={-5} max={20} value={t.letterSpacing ?? ''} placeholder="auto" onChange={(e) => setTitle({ letterSpacing: numOrNull(e.target.value) })} className={num} />
              </div>
            </div>
            <div>
              <label className={lbl}>Color (empty = footer text color)</label>
              <div className="flex gap-1.5">
                <input type="color" value={t.color || '#000000'} onChange={(e) => setTitle({ color: e.target.value })} className="h-6 w-8 cursor-pointer rounded border border-base-300 p-0" />
                <input type="text" value={t.color || ''} onChange={(e) => setTitle({ color: e.target.value })} placeholder="#000000" className={num} />
              </div>
            </div>
            <div>
              <label className={lbl}>Padding (px): top · right · bottom · left</label>
              <div className="grid grid-cols-4 gap-1">
                {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
                  <input key={side} type="number" min={0} max={200} value={pad[side] ?? ''} placeholder="0" title={side}
                    onChange={(e) => setTitle({ padding: { ...pad, [side]: e.target.value === '' ? undefined : Number(e.target.value) } })} className={num} />
                ))}
              </div>
            </div>
            <div>
              <label className={lbl + ' flex justify-between'}><span>Space between title and icons</span><span>{t.gap ?? 8}px</span></label>
              <input type="range" min={0} max={120} value={t.gap ?? 8} onChange={(e) => setTitle({ gap: Number(e.target.value) })} className="range range-xs w-full" />
            </div>
          </>)}
        </div>
      </details>
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <label className="text-[11px] text-base-content/50">Icons / links ({links.length})</label>
          <button type="button" onClick={addLink} className="btn btn-xs btn-primary gap-1"><Plus size={12} /> Add icon</button>
        </div>
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
        <button type="button" onClick={addLink} className="btn btn-xs btn-ghost border border-base-300 w-full gap-1">
          <Plus size={12} /> Add icon
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
          <label className="text-[11px] text-base-content/50 mb-1 block">Size preset</label>
          <select value={(d.size as string) || 'md'} onChange={(e) => onUpdate({ ...block.data, size: e.target.value, iconSize: null })} className="select select-bordered select-sm w-full text-[12px]">
            <option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option>
          </select>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className={lbl}>Background behind icons</label>
          <select value={iconBgMode} onChange={(e) => update('iconBg', e.target.value === 'custom' ? '#ffffff' : e.target.value)} className={sel}
            disabled={style !== 'circle' && style !== 'square'} title={style !== 'circle' && style !== 'square' ? 'Only for Circle / Square style' : ''}>
            <option value="">Theme default</option><option value="none">Off (no background)</option><option value="custom">Custom color</option>
          </select>
        </div>
        <div>
          <label className={lbl}>Shadow</label>
          <select value={(d.shadow as string) || 'none'} onChange={(e) => update('shadow', e.target.value)} className={sel}>
            <option value="none">None</option><option value="sm">Soft</option><option value="md">Medium</option><option value="lg">Strong</option>
          </select>
        </div>
      </div>
      {iconBgMode === 'custom' && (
        <div className="flex gap-1.5">
          <input type="color" value={String(d.iconBg).slice(0, 7)} onChange={(e) => update('iconBg', e.target.value)} className="h-6 w-8 cursor-pointer rounded border border-base-300 p-0" />
          <input type="text" value={(d.iconBg as string) || ''} onChange={(e) => update('iconBg', e.target.value)} className={num} />
        </div>
      )}
      <p className="text-[10px] text-base-content/40 -mt-2">With the background off, the shadow follows the icon's own shape (transparent PNG/SVG).</p>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 flex justify-between">
          <span>Icon size</span><span>{iconPx}px</span>
        </label>
        <div className="flex items-center gap-2">
          <input type="range" min={10} max={160} value={iconPx} onChange={(e) => update('iconSize', Number(e.target.value))} className="range range-xs flex-1" />
          <input type="number" min={10} max={160} value={iconPx}
            onChange={(e) => { const n = Math.round(Number(e.target.value)); if (n >= 10 && n <= 160) update('iconSize', n); }}
            className="input input-bordered input-xs w-16 text-[12px]" />
        </div>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 flex justify-between">
          <span>Space between icons</span><span>{gapPx}px</span>
        </label>
        <input type="range" min={0} max={80} value={gapPx} onChange={(e) => update('gap', Number(e.target.value))} className="range range-xs w-full" />
      </div>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" className="checkbox checkbox-xs" checked={!!d.showLabels} onChange={(e) => update('showLabels', e.target.checked)} /> Show labels next to icons
      </label>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Icon color (empty = text color)</label>
        <input type="text" value={(d.color as string) || ''} onChange={(e) => update('color', e.target.value)} placeholder="#000000" className="input input-bordered input-sm w-full text-[12px]" />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Icons alignment</label>
        <select value={(d.align as string) || 'left'} onChange={(e) => update('align', e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="left">Left</option><option value="center">Center</option><option value="right">Right</option>
        </select>
      </div>
    </div>
  );
};
