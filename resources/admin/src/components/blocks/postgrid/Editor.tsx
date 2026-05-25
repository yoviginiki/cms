import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { categories as categoriesApi } from '@/lib/api';
import type { BlockEditorProps } from '@/types/blocks';

const FONT_OPTIONS = [
  'inherit', 'Inter', 'Georgia', 'Arial', 'Helvetica', 'Times New Roman',
  'Verdana', 'Trebuchet MS', 'Courier New', 'Roboto', 'Open Sans', 'Lato',
  'Montserrat', 'Playfair Display', 'Merriweather', 'Poppins', 'Raleway',
];

const ALIGN_OPTIONS = ['left', 'center', 'right'] as const;

interface PostgridData {
  categoryId: string; limit: number; columns: number; cardStyle: string; gap: number;
  // Image
  showImage: boolean; imageHeight: number; imageWidth: string;
  // Heading
  showHeading: boolean; headingTag: string; headingSize: number; headingFont: string;
  headingAlign: string; headingPadding: string; headingMargin: string;
  // Excerpt
  showExcerpt: boolean; excerptSize: number; excerptFont: string;
  excerptAlign: string; excerptPadding: string; excerptMargin: string;
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
  const data = block.data as PostgridData;
  const { siteId = '' } = useParams();

  const { data: cats } = useQuery<Array<{ id: string; name: string }>>({
    queryKey: ['categories-for-block', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
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

      {/* ─── Image ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <div className="flex items-center justify-between mb-2">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Image</div>
          <label className="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={data.showImage !== false} onChange={(e) => update('showImage', e.target.checked)} />
          </label>
        </div>
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
          </div>
        )}
      </div>

      {/* ─── Heading ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <div className="flex items-center justify-between mb-2">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Heading</div>
          <label className="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={data.showHeading !== false} onChange={(e) => update('showHeading', e.target.checked)} />
          </label>
        </div>
        {data.showHeading !== false && (
          <div className="space-y-2">
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
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Padding</label>
                <input type="text" className="input input-bordered input-xs w-full" value={data.headingPadding || ''} onChange={(e) => update('headingPadding', e.target.value)} placeholder="0" />
              </div>
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Margin</label>
                <input type="text" className="input input-bordered input-xs w-full" value={data.headingMargin || ''} onChange={(e) => update('headingMargin', e.target.value)} placeholder="0 0 0.25rem 0" />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* ─── Excerpt ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <div className="flex items-center justify-between mb-2">
          <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Excerpt</div>
          <label className="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" className="toggle toggle-xs toggle-primary" checked={!!data.showExcerpt} onChange={(e) => update('showExcerpt', e.target.checked)} />
          </label>
        </div>
        {data.showExcerpt && (
          <div className="space-y-2">
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
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Padding</label>
                <input type="text" className="input input-bordered input-xs w-full" value={data.excerptPadding || ''} onChange={(e) => update('excerptPadding', e.target.value)} placeholder="0" />
              </div>
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Margin</label>
                <input type="text" className="input input-bordered input-xs w-full" value={data.excerptMargin || ''} onChange={(e) => update('excerptMargin', e.target.value)} placeholder="0.25rem 0 0 0" />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
