import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { categories as categoriesApi } from '@/lib/api';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';
import type { BlockEditorProps } from '@/types/blocks';

const FONT_OPTIONS = [
  'inherit', 'Inter', 'Georgia', 'Arial', 'Helvetica', 'Times New Roman',
  'Verdana', 'Trebuchet MS', 'Courier New', 'Roboto', 'Open Sans', 'Lato',
  'Montserrat', 'Playfair Display', 'Merriweather', 'Poppins', 'Raleway',
];

const ALIGN_OPTIONS = ['left', 'center', 'right'] as const;

interface PostgridData {
  categoryId: string; limit: number; columns: number; cardStyle: string; gap: number;
  // Card border
  cardBorder: boolean; cardBorderWidth: number; cardBorderColor: string;
  cardBorderRadius: number; cardBorderStyle: string; cardShadow: string;
  cardBg: string; cardPadding: string;
  // Content toggles
  showDate: boolean; showAuthor: boolean; showCategory: boolean;
  // Image
  showImage: boolean; imageHeight: number; imageWidth: string; imageObjectFit: string;
  // Heading
  showHeading: boolean; headingPosition: string; headingVerticalDir: string;
  headingTag: string; headingSize: number; headingFont: string;
  headingAlign: string; headingPadding: string; headingMargin: string;
  // Excerpt
  showExcerpt: boolean; excerptLength: number; excerptSize: number; excerptFont: string;
  excerptAlign: string; excerptPadding: string; excerptMargin: string;
  // Effects
  effects: CardEffects;
}

function RangeField({ label, value, min, max, step, unit, onChange }: {
  label: string; value: number; min: number; max: number; step: number; unit: string;
  onChange: (v: number) => void;
}) {
  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1 block">{label}</label>
      <input type="range" className="range range-xs range-primary w-full"
        min={min} max={max} step={step} value={value} onChange={(e) => onChange(Number(e.target.value))} />
      <div className="flex justify-between text-[9px] text-base-content/30 mt-0.5">
        <span>{min}{unit}</span>
        <span className="font-medium text-base-content/50">{value}{unit}</span>
        <span>{max}{unit}</span>
      </div>
    </div>
  );
}

function SpacingField({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  // Parse "10px 20px 10px 20px" or "10px" into 4 values
  const parts = (value || '0').trim().split(/\s+/);
  const top = parts[0] || '0';
  const right = parts[1] || top;
  const bottom = parts[2] || top;
  const left = parts[3] || right;

  const rebuild = (t: string, r: string, b: string, l: string) => {
    const nt = t || '0', nr = r || '0', nb = b || '0', nl = l || '0';
    if (nt === nr && nr === nb && nb === nl) return nt;
    if (nt === nb && nr === nl) return `${nt} ${nr}`;
    return `${nt} ${nr} ${nb} ${nl}`;
  };

  return (
    <div>
      <label className="text-[10px] text-base-content/40 mb-1 block">{label}</label>
      <div className="grid grid-cols-4 gap-1">
        {[
          { lbl: 'T', val: top, set: (v: string) => onChange(rebuild(v, right, bottom, left)) },
          { lbl: 'R', val: right, set: (v: string) => onChange(rebuild(top, v, bottom, left)) },
          { lbl: 'B', val: bottom, set: (v: string) => onChange(rebuild(top, right, v, left)) },
          { lbl: 'L', val: left, set: (v: string) => onChange(rebuild(top, right, bottom, v)) },
        ].map(s => (
          <div key={s.lbl} className="relative">
            <span className="absolute top-0.5 left-1 text-[7px] text-base-content/20">{s.lbl}</span>
            <input type="text" className="input input-bordered input-xs w-full text-[10px] text-center pt-2.5"
              value={s.val} onChange={(e) => s.set(e.target.value)} placeholder="0" />
          </div>
        ))}
      </div>
    </div>
  );
}

function AlignButtons({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <div className="flex gap-0.5">
      {ALIGN_OPTIONS.map(a => (
        <button key={a} type="button" onClick={() => onChange(a)}
          className={`btn btn-xs flex-1 text-[10px] ${value === a ? 'btn-primary' : 'btn-ghost'}`}>
          {a[0].toUpperCase() + a.slice(1)}
        </button>
      ))}
    </div>
  );
}

export const PostgridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as unknown as PostgridData;
  const { siteId = '' } = useParams();

  const { data: cats } = useQuery<Array<{ id: string; name: string }>>({
    queryKey: ['categories-for-block', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => {
      // Flatten category tree to flat list with indented names
      const flat: Array<{ id: string; name: string }> = [];
      const walk = (items: any[], depth = 0) => {
        for (const c of items) {
          flat.push({ id: c.id, name: (depth > 0 ? '  '.repeat(depth) + '↳ ' : '') + c.name });
          if (c.children?.length) walk(c.children, depth + 1);
        }
      };
      walk(r.data.data || []);
      return flat;
    }),
    enabled: !!siteId,
  });

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      {/* ─── Grid Settings ─── */}
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Category</label>
        <select className="select select-bordered select-sm w-full" value={data.categoryId || ''} onChange={(e) => update('categoryId', e.target.value)}>
          <option value="">All categories</option>
          {(cats || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Limit</label>
          <input type="number" className="input input-bordered input-sm w-full" value={data.limit || 9} onChange={(e) => update('limit', Number(e.target.value))} min={1} max={50} />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
          <select className="select select-bordered select-sm w-full" value={data.columns || 3} onChange={(e) => update('columns', Number(e.target.value))}>
            {[1,2,3,4,5,6].map(n => <option key={n} value={n}>{n}</option>)}
          </select>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Card Style</label>
          <select className="select select-bordered select-sm w-full" value={data.cardStyle || 'vertical'} onChange={(e) => update('cardStyle', e.target.value)}>
            <option value="vertical">Vertical</option>
            <option value="horizontal">Horizontal</option>
          </select>
        </div>
        <div>
          <RangeField label="Gap" value={data.gap ?? 24} min={0} max={64} step={4} unit="px" onChange={(v) => update('gap', v)} />
        </div>
      </div>

      {/* ─── Card Style ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Card Border</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.cardBorder !== false ? 'On' : 'Off'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={data.cardBorder !== false} onChange={(e) => update('cardBorder', e.target.checked)} />
          </div>
        </label>
        {data.cardBorder !== false && (
          <div className="space-y-2">
            <div className="grid grid-cols-2 gap-2">
              <div>
                <RangeField label="Width" value={data.cardBorderWidth ?? 1} min={0} max={8} step={1} unit="px" onChange={(v) => update('cardBorderWidth', v)} />
              </div>
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
                <select className="select select-bordered select-xs w-full" value={data.cardBorderStyle || 'solid'} onChange={(e) => update('cardBorderStyle', e.target.value)}>
                  <option value="solid">Solid</option>
                  <option value="dashed">Dashed</option>
                  <option value="dotted">Dotted</option>
                  <option value="double">Double</option>
                  <option value="none">None</option>
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Color</label>
                <div className="flex gap-1">
                  <input type="color" className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                    value={data.cardBorderColor || '#e5e7eb'} onChange={(e) => update('cardBorderColor', e.target.value)} />
                  <input type="text" className="input input-bordered input-xs flex-1 font-mono text-[10px]"
                    value={data.cardBorderColor || '#e5e7eb'} onChange={(e) => update('cardBorderColor', e.target.value)} placeholder="#e5e7eb" />
                </div>
              </div>
              <div>
                <RangeField label="Radius" value={data.cardBorderRadius ?? 12} min={0} max={32} step={2} unit="px" onChange={(v) => update('cardBorderRadius', v)} />
              </div>
            </div>
          </div>
        )}

        <div className="mt-2 space-y-2">
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Shadow</label>
            <select className="select select-bordered select-xs w-full" value={data.cardShadow || 'none'} onChange={(e) => update('cardShadow', e.target.value)}>
              <option value="none">None</option>
              <option value="sm">Small</option>
              <option value="md">Medium</option>
              <option value="lg">Large</option>
              <option value="xl">Extra Large</option>
            </select>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Card Background</label>
            <div className="flex gap-1">
              <input type="color" className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                value={data.cardBg || '#ffffff'} onChange={(e) => update('cardBg', e.target.value)} />
              <input type="text" className="input input-bordered input-xs flex-1 font-mono text-[10px]"
                value={data.cardBg || ''} onChange={(e) => update('cardBg', e.target.value)} placeholder="transparent" />
            </div>
          </div>
          <SpacingField label="Card Padding" value={data.cardPadding || '0'} onChange={(v) => update('cardPadding', v)} />
        </div>
      </div>

      {/* ─── Image ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Image</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showImage !== false ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={data.showImage !== false} onChange={(e) => update('showImage', e.target.checked)} />
          </div>
        </label>
        {data.showImage !== false && (
          <div className="space-y-2">
            <RangeField label="Height" value={data.imageHeight || 160} min={40} max={600} step={10} unit="px" onChange={(v) => update('imageHeight', v)} />
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Width</label>
              <select className="select select-bordered select-xs w-full" value={data.imageWidth || '100%'} onChange={(e) => update('imageWidth', e.target.value)}>
                <option value="100%">Full width (100%)</option>
                <option value="75%">75%</option>
                <option value="50%">50%</option>
                <option value="33%">33%</option>
                <option value="auto">Auto (natural)</option>
              </select>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Fit</label>
              <select className="select select-bordered select-xs w-full" value={data.imageObjectFit || 'cover'} onChange={(e) => update('imageObjectFit', e.target.value)}>
                <option value="cover">Cover (fill, may crop)</option>
                <option value="contain">Contain (fit, may letterbox)</option>
                <option value="fill">Stretch (distort to fill)</option>
                <option value="scale-down">Scale down (never enlarge)</option>
                <option value="none">None (natural size)</option>
              </select>
            </div>
          </div>
        )}
      </div>

      {/* ─── Heading ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Heading</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showHeading !== false ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={data.showHeading !== false} onChange={(e) => update('showHeading', e.target.checked)} />
          </div>
        </label>
        {data.showHeading !== false && (
          <div className="space-y-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Position</label>
              <select className="select select-bordered select-xs w-full" value={data.headingPosition || 'below'} onChange={(e) => update('headingPosition', e.target.value)}>
                <option value="above">Above image</option>
                <option value="below">Below image</option>
                <option value="vertical-left">Vertical left</option>
                <option value="vertical-right">Vertical right</option>
              </select>
            </div>
            {(data.headingPosition === 'vertical-left' || data.headingPosition === 'vertical-right') && (
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Text Direction</label>
                <select className="select select-bordered select-xs w-full" value={data.headingVerticalDir || 'up'} onChange={(e) => update('headingVerticalDir', e.target.value)}>
                  <option value="up">Up ↑</option>
                  <option value="down">Down ↓</option>
                  <option value="left">Left ←</option>
                  <option value="right">Right →</option>
                  <option value="stacked">Stacked (letters upright)</option>
                </select>
              </div>
            )}
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Tag</label>
                <select className="select select-bordered select-xs w-full" value={data.headingTag || 'h3'} onChange={(e) => update('headingTag', e.target.value)}>
                  <option value="h2">H2</option>
                  <option value="h3">H3</option>
                  <option value="h4">H4</option>
                </select>
              </div>
              <div>
                <RangeField label="Size" value={data.headingSize || 16} min={10} max={48} step={1} unit="px" onChange={(v) => update('headingSize', v)} />
              </div>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Font</label>
              <select className="select select-bordered select-xs w-full" value={data.headingFont || 'inherit'} onChange={(e) => update('headingFont', e.target.value)}>
                {FONT_OPTIONS.map(f => <option key={f} value={f}>{f}</option>)}
              </select>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
              <AlignButtons value={data.headingAlign || 'left'} onChange={(v) => update('headingAlign', v)} />
            </div>
            <SpacingField label="Padding" value={data.headingPadding || '0'} onChange={(v) => update('headingPadding', v)} />
            <SpacingField label="Margin" value={data.headingMargin || '0 0 0.25rem 0'} onChange={(v) => update('headingMargin', v)} />
          </div>
        )}
      </div>

      {/* ─── Excerpt ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Excerpt</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showExcerpt ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!data.showExcerpt} onChange={(e) => update('showExcerpt', e.target.checked)} />
          </div>
        </label>
        {data.showExcerpt && (
          <div className="space-y-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Character limit</label>
              <div className="flex items-center gap-2">
                <input type="number" className="input input-bordered input-xs w-20" min={0} max={1000}
                  value={data.excerptLength ?? 120} onChange={(e) => update('excerptLength', Number(e.target.value))} />
                <span className="text-[9px] text-base-content/30">0 = full excerpt</span>
              </div>
            </div>
            <RangeField label="Size" value={data.excerptSize || 14} min={10} max={32} step={1} unit="px" onChange={(v) => update('excerptSize', v)} />
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Font</label>
              <select className="select select-bordered select-xs w-full" value={data.excerptFont || 'inherit'} onChange={(e) => update('excerptFont', e.target.value)}>
                {FONT_OPTIONS.map(f => <option key={f} value={f}>{f}</option>)}
              </select>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
              <AlignButtons value={data.excerptAlign || 'left'} onChange={(v) => update('excerptAlign', v)} />
            </div>
            <SpacingField label="Padding" value={data.excerptPadding || '0'} onChange={(v) => update('excerptPadding', v)} />
            <SpacingField label="Margin" value={data.excerptMargin || '0.25rem 0 0 0'} onChange={(v) => update('excerptMargin', v)} />
          </div>
        )}
      </div>

      {/* ─── Date ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Date</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showDate ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!data.showDate} onChange={(e) => update('showDate', e.target.checked)} />
          </div>
        </label>
      </div>

      {/* ─── Author ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Author</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showAuthor ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!data.showAuthor} onChange={(e) => update('showAuthor', e.target.checked)} />
          </div>
        </label>
      </div>

      {/* ─── Category ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="flex items-center justify-between mb-2 cursor-pointer">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Category</div>
          <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-base-content/30">{data.showCategory ? 'Visible' : 'Hidden'}</span>
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!data.showCategory} onChange={(e) => update('showCategory', e.target.checked)} />
          </div>
        </label>
      </div>

      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={data.effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>
    </div>
  );
};
