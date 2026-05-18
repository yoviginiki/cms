import type { BlockComponentProps } from '@/types/blocks';

export const PostLoopPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const layout = (data.layout as string) || 'cards';
  const columns = Number(data.columns) || 3;
  const showImage = data.showImage !== false;
  const showExcerpt = data.showExcerpt !== false;

  const cards = Array.from({ length: Math.min(columns, 6) }, (_, i) => i);

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium z-10">Dynamic</div>
      {layout === 'list' ? (
        <div className="space-y-3">
          {cards.map(i => (
            <div key={i} className="flex gap-3 p-2 border border-gray-100 rounded">
              {showImage && <div className="w-20 h-14 bg-gray-200 rounded shrink-0" />}
              <div className="flex-1 space-y-1">
                <div className="h-3 bg-gray-300 rounded w-3/4" />
                <div className="h-2 bg-gray-100 rounded w-1/2" />
                {showExcerpt && <div className="h-2 bg-gray-100 rounded w-full" />}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
          {cards.map(i => (
            <div key={i} className="border border-gray-100 rounded overflow-hidden">
              {showImage && <div className="h-24 bg-gray-200" />}
              <div className="p-2 space-y-1">
                <div className="h-3 bg-gray-300 rounded w-5/6" />
                <div className="h-2 bg-gray-100 rounded w-1/2" />
                {showExcerpt && <div className="h-2 bg-gray-100 rounded w-full" />}
              </div>
            </div>
          ))}
        </div>
      )}
      <p className="text-center text-xs text-indigo-400 mt-2 font-medium">Post Loop — {layout} layout</p>
    </div>
  );
};
