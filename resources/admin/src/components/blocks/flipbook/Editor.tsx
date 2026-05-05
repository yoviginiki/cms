import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { BlockEditorProps } from '@/types/blocks';
import type { FlipbookBlockData } from './definition';
import { AssetField } from '@/components/ui/AssetPicker';
import { categories } from '@/lib/api';

export const FlipbookEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as unknown as FlipbookBlockData;
  const { siteId = '' } = useParams();

  const update = (field: keyof FlipbookBlockData, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const pdfUrl = data.pdf_url || (data.pdf_asset_id ? `/api/v1/assets/${data.pdf_asset_id}/serve` : '');

  // Fetch categories for the dropdown
  const { data: cats } = useQuery({
    queryKey: ['categories', siteId],
    queryFn: () => categories.list(siteId).then((r: any) => r.data.data || r.data),
    enabled: !!siteId,
  });
  const categoryList = (Array.isArray(cats) ? cats : []) as { id: string; name: string; slug: string }[];

  return (
    <div className="space-y-4">
      {/* ─── Content Source ─── */}
      <div>
        <label className="block text-[11px] font-medium text-base-content/60 mb-1.5">Content source</label>
        <div className="flex rounded-lg overflow-hidden border border-base-300/30">
          {([
            { value: 'pdf', label: 'PDF' },
            { value: 'category', label: 'Category' },
            { value: 'children', label: 'Blocks' },
          ] as const).map(s => (
            <button key={s.value} onClick={() => update('source', s.value)}
              className={`flex-1 px-2 py-1.5 text-[11px] font-medium transition-colors ${
                (data.source ?? 'pdf') === s.value ? 'bg-primary text-primary-content' : 'bg-base-200/50 text-base-content/50 hover:text-base-content/70'
              }`}>
              {s.label}
            </button>
          ))}
        </div>
      </div>

      {/* PDF source */}
      {(data.source ?? 'pdf') === 'pdf' && (
        <div className="border-b border-base-300/20 pb-4">
          <AssetField
            label="Upload or choose PDF"
            value={pdfUrl}
            onChange={(url, assetId) => {
              onUpdate({ ...block.data, pdf_url: url, pdf_asset_id: assetId || null });
            }}
            accept="all"
          />
          {pdfUrl && <p className="text-[10px] text-success mt-1">PDF loaded.</p>}
        </div>
      )}

      {/* Category source */}
      {data.source === 'category' && (
        <div className="border-b border-base-300/20 pb-4 space-y-3">
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Category</label>
            <select
              value={data.category_id || ''}
              onChange={(e) => update('category_id', e.target.value || null)}
              className="select select-bordered select-sm w-full text-[12px]">
              <option value="">Select a category...</option>
              {categoryList.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Order</label>
            <select value={data.posts_order ?? 'date_desc'} onChange={(e) => update('posts_order', e.target.value)}
              className="select select-bordered select-sm w-full text-[12px]">
              <option value="date_desc">Newest first</option>
              <option value="date_asc">Oldest first</option>
              <option value="title_asc">Title A-Z</option>
              <option value="title_desc">Title Z-A</option>
            </select>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Max articles</label>
            <input type="number" min={2} max={200} value={data.posts_limit ?? 50}
              onChange={(e) => update('posts_limit', Number(e.target.value))}
              className="input input-bordered input-sm w-full text-[12px]" />
          </div>
          {data.category_id && (
            <p className="text-[10px] text-success">Each article in this category becomes one flipbook page.</p>
          )}
        </div>
      )}

      {/* Blocks source */}
      {data.source === 'children' && (
        <div className="border-b border-base-300/20 pb-3">
          <p className="text-[10px] text-base-content/30">Add child blocks below — each child becomes one page.</p>
        </div>
      )}

      {/* ─── Animation ─── */}
      <div>
        <label className="block text-[11px] font-medium text-base-content/60 mb-1.5">Animation</label>
        <div className="flex rounded-lg overflow-hidden border border-base-300/30">
          {(['realistic', 'minimal'] as const).map(m => (
            <button key={m} onClick={() => update('mode', m)}
              className={`flex-1 px-3 py-1.5 text-[11px] font-medium transition-colors ${
                data.mode === m ? 'bg-primary text-primary-content' : 'bg-base-200/50 text-base-content/50'
              }`}>
              {m === 'realistic' ? 'Realistic' : 'Minimal'}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="block text-[11px] font-medium text-base-content/60 mb-1">
          Speed: <span className="font-normal text-base-content/40">{data.flipping_time_ms}ms</span>
        </label>
        <input type="range" min={200} max={2000} step={50} value={data.flipping_time_ms}
          onChange={(e) => update('flipping_time_ms', Number(e.target.value))}
          className="range range-sm range-primary w-full" />
      </div>

      {/* ─── Navigation ─── */}
      <div className="border-t border-base-300/20 pt-3 space-y-2">
        <label className="block text-[11px] font-medium text-base-content/60 mb-1">Navigation</label>
        {([
          { field: 'show_nav_bar' as const, label: 'Bottom navigation bar' },
          { field: 'show_fullscreen' as const, label: 'Fullscreen button' },
          { field: 'show_page_indicator' as const, label: 'Page indicator' },
          { field: 'click_to_flip' as const, label: 'Click to flip' },
          { field: 'swipe_to_flip' as const, label: 'Swipe to flip' },
          { field: 'show_cover' as const, label: 'Hard covers' },
        ]).map(t => (
          <label key={t.field} className="flex items-center justify-between cursor-pointer">
            <span className="text-[11px] text-base-content/60">{t.label}</span>
            <input type="checkbox" checked={(data[t.field] as boolean) ?? true}
              onChange={(e) => update(t.field, e.target.checked)}
              className="toggle toggle-xs toggle-primary" />
          </label>
        ))}
      </div>

      {data.mode === 'realistic' && (
        <div>
          <label className="block text-[11px] font-medium text-base-content/60 mb-1">
            Shadow: {Math.round(data.max_shadow_opacity * 100)}%
          </label>
          <input type="range" min={0} max={1} step={0.05} value={data.max_shadow_opacity}
            onChange={(e) => update('max_shadow_opacity', Number(e.target.value))}
            className="range range-sm range-primary w-full" />
        </div>
      )}
    </div>
  );
};
