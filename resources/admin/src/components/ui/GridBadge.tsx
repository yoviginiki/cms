import { Link } from 'react-router-dom';
import { LayoutGrid } from 'lucide-react';

/** Server's EffectiveGridResolver::forContent() — what the page/post is actually published with. */
export interface EffectiveGrid {
  grid: { id: string; name: string; slug: string } | null;
  source: 'override' | 'category' | 'page' | 'post' | 'post_type' | 'rule' | 'default' | 'none' | 'skipped';
  reason: string | null;
  label: string;
}

const SOURCE_LABEL: Record<string, string> = {
  override: 'set on this item',
  category: 'from its category',
  page: 'assigned to this page',
  post: 'assigned to this post',
  post_type: 'assigned to all of this type',
  rule: 'URL rule',
  default: 'site default',
};

export function GridBadge({ siteId, effective }: { siteId: string; effective?: EffectiveGrid | null }) {
  if (!effective) return <span className="text-[11px] text-base-content/20">--</span>;

  if (!effective.grid) {
    return (
      <span className="text-[11px] text-base-content/40" title={effective.label}>
        No grid
      </span>
    );
  }

  const source = SOURCE_LABEL[effective.source] ?? effective.source;
  return (
    <Link
      to={`/sites/${siteId}/grids/${effective.grid.id}/edit`}
      title={`Published with the “${effective.grid.name}” grid (${source}). Header and footer come from this grid's areas.`}
      className="inline-flex flex-col items-start gap-0.5 group"
    >
      <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-base-200 text-base-content/70 group-hover:bg-blue-50 group-hover:text-blue-700">
        <LayoutGrid className="h-3 w-3" /> {effective.grid.name}
      </span>
      <span className="text-[10px] text-base-content/35 pl-1">{source}</span>
    </Link>
  );
}
