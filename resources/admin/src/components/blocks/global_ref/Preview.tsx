import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Boxes } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';
import { globalSections, type GlobalSectionSummary } from '@/lib/api';

/**
 * Canvas placeholder: static pages inline the published global section at build
 * time; the page canvas shows a labeled stand-in (a global is edited in its own
 * place, not on the page — that's the whole point of "edit once, everywhere").
 */
export const GlobalRefPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { siteId = '' } = useParams();
  const sectionId = (block.data as Record<string, unknown>).sectionId as string | null;

  const { data: list } = useQuery<GlobalSectionSummary[]>({
    queryKey: ['global-sections', siteId],
    queryFn: () => globalSections.list(siteId).then((r) => r.data.data),
    enabled: !!sectionId,
  });
  const selected = list?.find((s) => s.id === sectionId);

  return (
    <div className="w-full bg-base-300/40 border border-dashed border-primary/30 text-base-content flex flex-col items-center justify-center gap-2"
      style={{ minHeight: 140 }}>
      <Boxes size={26} className="opacity-40 text-primary" />
      {selected ? (
        <>
          <span className="text-sm font-medium">{selected.name}</span>
          <span className={`text-[10px] uppercase tracking-wider ${selected.status === 'published' ? 'text-green-600/70' : 'text-amber-600/70'}`}>
            global section · {selected.status === 'published' ? 'published' : 'draft (not rendered)'}
          </span>
        </>
      ) : (
        <span className="text-xs opacity-50">No global section selected</span>
      )}
    </div>
  );
};
