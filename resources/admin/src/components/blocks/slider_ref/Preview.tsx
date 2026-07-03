import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { GalleryHorizontalEnd } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';
import { sliders } from '@/lib/api';

/**
 * Canvas placeholder: static pages inline the published slider at build time;
 * the page canvas shows a labeled stand-in (the real preview lives in the
 * slider editor / preview endpoint — no second renderer).
 */
export const SliderRefPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { siteId = '' } = useParams();
  const sliderId = (block.data as Record<string, unknown>).sliderId as string | null;

  const { data: list } = useQuery<{ id: string; name: string; status: string }[]>({
    queryKey: ['sliders', siteId],
    queryFn: () => sliders.list(siteId).then(r => r.data.data),
    enabled: !!sliderId,
  });
  const selected = list?.find(s => s.id === sliderId);

  return (
    <div className="w-full bg-neutral-900 text-neutral-100 flex flex-col items-center justify-center gap-2"
      style={{ minHeight: 220 }}>
      <GalleryHorizontalEnd size={28} className="opacity-40" />
      {selected ? (
        <>
          <span className="text-sm font-medium">{selected.name}</span>
          <span className={`text-[10px] uppercase tracking-wider ${selected.status === 'published' ? 'text-green-400/70' : 'text-amber-400/70'}`}>
            {selected.status === 'published' ? 'slider · published' : 'slider · draft (not rendered)'}
          </span>
        </>
      ) : (
        <span className="text-xs opacity-50">No slider selected</span>
      )}
    </div>
  );
};
