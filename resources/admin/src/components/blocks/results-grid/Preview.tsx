import type { BlockComponentProps } from '@/types/blocks';
import { useCollectionName } from '../collections-shared';

export const ResultsGridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const columns = Math.min(Math.max(Number(data.columns) || 3, 1), 6);
  const showImage = data.showImage !== false;
  const cardFieldCount = Math.min(((data.cardFields as string[]) || []).length, 6);
  const collectionName = useCollectionName(data.collectionId as string | null);

  const cards = Array.from({ length: columns }, (_, i) => i);

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium z-10">Dynamic</div>
      <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
        {cards.map(i => (
          <div key={i} className="border border-dashed border-gray-200 rounded overflow-hidden">
            {showImage && <div className="h-24 bg-gray-200" />}
            <div className="p-2 space-y-1">
              <div className="h-3 bg-gray-300 rounded w-5/6" />
              {Array.from({ length: Math.max(cardFieldCount, 1) }, (_, j) => (
                <div key={j} className="h-2 bg-gray-100 rounded w-1/2" />
              ))}
            </div>
          </div>
        ))}
      </div>
      <p className="text-center text-xs text-indigo-400 mt-2 font-medium">
        Results Grid — {collectionName || 'inherited collection'} · populated by search
      </p>
    </div>
  );
};
