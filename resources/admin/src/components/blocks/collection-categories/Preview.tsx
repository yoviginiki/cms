import type { BlockComponentProps } from '@/types/blocks';
import { useCollectionName } from '../collections-shared';

/** Canvas preview — skeleton category cards (real data renders on publish). */
export const CollectionCategoriesPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const layout = (data.layout as string) || 'cards';
  const columns = Number(data.columns ?? 4);
  const name = useCollectionName((data.collectionId as string | null) || null);
  const count = layout === 'cards' ? Math.max(columns, 4) : 6;

  return (
    <div className="p-2">
      <div className="text-[10px] text-base-content/40 mb-2">
        Category List{name ? ` — ${name}` : ''}{data.parentNodeId ? ' (subtree)' : ''}
      </div>
      {layout === 'pills' ? (
        <div className="flex flex-wrap gap-2">
          {Array.from({ length: count }).map((_, i) => (
            <div key={i} className="h-7 rounded-full bg-base-300/60 animate-none" style={{ width: 70 + (i % 3) * 30 }} />
          ))}
        </div>
      ) : layout === 'list' ? (
        <div className="space-y-1.5">
          {Array.from({ length: count }).map((_, i) => (
            <div key={i} className="h-9 rounded-lg bg-base-300/50 flex items-center px-3">
              <div className="h-2.5 w-32 rounded bg-base-content/10" />
            </div>
          ))}
        </div>
      ) : (
        <div className="grid gap-2" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
          {Array.from({ length: count }).map((_, i) => (
            <div key={i} className="h-20 rounded-xl bg-base-300/50 flex flex-col items-center justify-center gap-1.5">
              <div className="h-6 w-6 rounded-lg bg-base-content/10" />
              <div className="h-2 w-16 rounded bg-base-content/10" />
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
