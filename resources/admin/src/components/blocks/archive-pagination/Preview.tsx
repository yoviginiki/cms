import type { BlockComponentProps } from '@/types/blocks';

export const ArchivePaginationPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const style = (data.style as string) || 'numbered';
  const align = (data.align as string) || 'center';

  return (
    <div className="relative" style={{ textAlign: align as 'left' | 'center' | 'right' }}>
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      {style === 'numbered' ? (
        <div className="inline-flex gap-1 mt-2">
          <span className="px-2.5 py-1 text-xs bg-indigo-500 text-white rounded">1</span>
          <span className="px-2.5 py-1 text-xs bg-gray-100 text-gray-600 rounded">2</span>
          <span className="px-2.5 py-1 text-xs bg-gray-100 text-gray-600 rounded">3</span>
          <span className="px-2.5 py-1 text-xs bg-gray-100 text-gray-400 rounded">...</span>
          <span className="px-2.5 py-1 text-xs bg-gray-100 text-gray-600 rounded">12</span>
        </div>
      ) : style === 'load-more' ? (
        <button className="px-4 py-1.5 text-xs bg-gray-100 text-gray-600 rounded mt-2">Load More</button>
      ) : (
        <div className="inline-flex gap-3 mt-2 text-xs">
          <span className="text-gray-400">&larr; Previous</span>
          <span className="text-indigo-500">Next &rarr;</span>
        </div>
      )}
    </div>
  );
};
