import type { BlockComponentProps } from '@/types/blocks';

export const CategoryHeaderPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const textAlign = (data.textAlign as string) || 'center';

  return (
    <div className="relative" style={{ textAlign: textAlign as 'left' | 'center' | 'right' }}>
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <h1 className="text-2xl font-bold mb-1">Category Name</h1>
      {data.showDescription !== false && (
        <p className="text-sm text-gray-400">Category description text goes here</p>
      )}
      {!!data.showPostCount && (
        <p className="text-xs text-gray-300 mt-1">24 posts</p>
      )}
    </div>
  );
};
