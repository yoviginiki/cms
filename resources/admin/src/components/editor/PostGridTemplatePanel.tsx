import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { LayoutGrid, LayoutTemplate } from 'lucide-react';
import { grids as gridsApi, posts as postsApi } from '@/lib/api';

const SOURCE_LABELS: Record<string, string> = {
  override: 'избран за този пост',
  post: 'присвоен на този пост (Assignments)',
  category: 'от категорията на поста',
  post_type: 'за всички постове (Assignments)',
  rule: 'по URL правило (Assignments)',
  default: 'grid по подразбиране на сайта',
  none: 'няма grid',
};

/**
 * Post tab, top: which grid frames the post (inherit = category grid, else the
 * site default; or pick one for this post) and which post template shapes its
 * content (title, date, image … — never header/footer).
 */
export function PostGridTemplatePanel({ siteId, postId, layoutId, gridId, setGridId, templateId, setTemplateId, postTemplates }: {
  siteId: string; postId: string; layoutId: string;
  gridId: string; setGridId: (v: string) => void;
  templateId: string; setTemplateId: (v: string) => void;
  postTemplates: Array<{ id: string; name: string; is_default?: boolean }>;
}) {
  const navigate = useNavigate();
  const { data: resolved } = useQuery<any>({
    queryKey: ['resolved-grid-post', siteId, postId, gridId],
    queryFn: () => postsApi.resolvedGrid(siteId, postId).then((r: any) => r.data.data),
    enabled: !!postId,
  });
  const { data: gridList } = useQuery<any[]>({
    queryKey: ['grids', siteId],
    queryFn: () => gridsApi.list(siteId).then((r: any) => r.data.data),
  });
  const inheritedName = resolved && resolved.source !== 'override' ? resolved.grid?.name : null;

  return (
    <div className="p-3 space-y-3 border-b border-base-300/30" data-testid="post-grid-template">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 flex items-center gap-1"><LayoutGrid size={11} /> Grid</label>
        <select value={gridId} onChange={e => setGridId(e.target.value)} className="select select-bordered select-sm w-full text-[12px]">
          <option value="">{inheritedName ? `Наследен: ${inheritedName}` : 'Наследен (категория / сайт)'}</option>
          {(gridList || []).map((g: any) => <option key={g.id} value={g.id}>{g.name}</option>)}
        </select>
        {layoutId ? (
          <p className="text-[10px] text-warning mt-0.5">Layout горе не е Standard — grid-ът не се ползва.</p>
        ) : resolved && (
          <p className="text-[10px] text-base-content/40 mt-0.5">
            {resolved.source === 'skipped'
              ? <>Без grid · {resolved.label}</>
              : <>Излиза с <strong>{resolved.grid?.name || '—'}</strong> · {SOURCE_LABELS[resolved.source] || resolved.source}</>}
            {gridId && ' (запиши, за да се обнови)'}
          </p>
        )}
        {resolved?.grid && (
          <button type="button" onClick={() => navigate(`/sites/${siteId}/grids/${resolved.grid.id}/edit`)} className="text-[10px] text-primary mt-0.5">
            Редактирай grid „{resolved.grid.name}“ →
          </button>
        )}
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 flex items-center gap-1"><LayoutTemplate size={11} /> Template</label>
        <select value={templateId} onChange={e => setTemplateId(e.target.value)} className="select select-bordered select-sm w-full text-[12px]"
          title="How the post content is laid out (title, date, image …). Never header/footer — that is the grid.">
          <option value="default">Default</option>
          {postTemplates.map(t => <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (default)' : ''}</option>)}
          <option value="none">Empty (само съдържанието на редактора)</option>
        </select>
      </div>
    </div>
  );
}
