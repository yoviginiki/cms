import { useQuery } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { GalleryHorizontalEnd, ExternalLink, Plus } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { sliders } from '@/lib/api';
import { TextField } from '@/components/editor/fields/TextField';

interface SliderSummary {
  id: string;
  name: string;
  status: string;
  used_on: number;
}

/**
 * Page-side slider embed: a picker + optional height override. Deliberately
 * nothing else — all styling lives in the slider editor (library entity).
 */
export const SliderRefEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { siteId = '' } = useParams();
  const data = block.data as Record<string, unknown>;
  const heightOverride = (data.heightOverride as Record<string, string>) || {};
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const { data: list } = useQuery<SliderSummary[]>({
    queryKey: ['sliders', siteId],
    queryFn: () => sliders.list(siteId).then(r => r.data.data),
  });

  const selected = list?.find(s => s.id === data.sliderId);

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40">Slider</label>
        <select
          value={(data.sliderId as string) || ''}
          onChange={e => update('sliderId', e.target.value || null)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="">— choose from library —</option>
          {(list ?? []).map(s => (
            <option key={s.id} value={s.id}>
              {s.name} {s.status !== 'published' ? '(draft — will not render)' : ''}
            </option>
          ))}
        </select>
      </div>

      {selected && (
        <div className="flex items-center justify-between text-[10px] text-base-content/50 bg-base-200/60 px-2 py-1.5">
          <span className="flex items-center gap-1">
            <GalleryHorizontalEnd size={11} />
            {selected.status === 'published' ? 'Published' : 'Draft'} · used on {selected.used_on} page(s)
          </span>
          <Link to={`/sites/${siteId}/sliders/${selected.id}/edit`} className="link link-primary flex items-center gap-0.5">
            Edit <ExternalLink size={10} />
          </Link>
        </div>
      )}

      <Link to={`/sites/${siteId}/sliders`}
        className="inline-flex items-center gap-1 text-[10px] text-primary hover:underline">
        <Plus size={11} /> Create new slider in the library
      </Link>

      <div className="border-t border-base-300/20 pt-2">
        <p className="text-[10px] text-base-content/40 mb-1.5">Height override (optional — defaults come from the slider)</p>
        <div className="grid grid-cols-3 gap-1.5">
          <TextField label="Desktop" value={heightOverride.desktop || ''}
            onChange={v => update('heightOverride', { ...heightOverride, desktop: v || undefined })} placeholder="70vh" />
          <TextField label="Tablet" value={heightOverride.tablet || ''}
            onChange={v => update('heightOverride', { ...heightOverride, tablet: v || undefined })} placeholder="60vh" />
          <TextField label="Mobile" value={heightOverride.mobile || ''}
            onChange={v => update('heightOverride', { ...heightOverride, mobile: v || undefined })} placeholder="80vh" />
        </div>
      </div>
    </div>
  );
};
