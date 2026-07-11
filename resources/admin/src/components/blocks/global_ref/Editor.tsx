import { useQuery } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Boxes, ExternalLink, AlertTriangle } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { globalSections, type GlobalSectionSummary } from '@/lib/api';

/**
 * Page-side global-section embed: just a picker. The section is edited in the
 * Global Sections library (not here) — editing it updates every page that
 * embeds it, which the banner makes explicit.
 */
export const GlobalRefEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { siteId = '' } = useParams();
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const { data: list } = useQuery<GlobalSectionSummary[]>({
    queryKey: ['global-sections', siteId],
    queryFn: () => globalSections.list(siteId).then((r) => r.data.data),
  });

  const selected = list?.find((s) => s.id === data.sectionId);

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40">Global section</label>
        <select
          value={(data.sectionId as string) || ''}
          onChange={(e) => update('sectionId', e.target.value || null)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="">— choose from library —</option>
          {(list ?? []).map((s) => (
            <option key={s.id} value={s.id}>
              {s.name} {s.status !== 'published' ? '(draft — will not render)' : ''}
            </option>
          ))}
        </select>
      </div>

      {selected && (
        <>
          <div className="flex items-center justify-between text-[10px] text-base-content/50 bg-base-200/60 px-2 py-1.5">
            <span className="flex items-center gap-1">
              <Boxes size={11} />
              {selected.status === 'published' ? 'Published' : 'Draft'} · used on {selected.used_on} page(s)
            </span>
            <Link to={`/sites/${siteId}/global-sections`} className="link link-primary flex items-center gap-0.5">
              Manage <ExternalLink size={10} />
            </Link>
          </div>
          {selected.used_on > 1 && (
            <div className="flex items-start gap-1.5 text-[10px] text-amber-700 bg-amber-500/10 px-2 py-1.5 border-l-2 border-amber-500">
              <AlertTriangle size={11} className="mt-px shrink-0" />
              <span>This is a global — editing it in the library updates all {selected.used_on} pages that embed it.</span>
            </div>
          )}
        </>
      )}

      <Link to={`/sites/${siteId}/global-sections`}
        className="inline-flex items-center gap-1 text-[10px] text-primary hover:underline">
        <Boxes size={11} /> Manage global sections
      </Link>
    </div>
  );
};
