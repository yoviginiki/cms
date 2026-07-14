import { Search } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';
import { useCollectionName } from '../collections-shared';

export const SearchBoxPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const collectionName = useCollectionName(data.collectionId as string | null);
  const placeholder = (data.placeholder as string) || (collectionName ? `Search ${collectionName}…` : 'Search…');

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium z-10">Dynamic</div>
      <div className="relative">
        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input type="search" disabled placeholder={placeholder}
          className="w-full border border-gray-300 rounded-md pl-9 pr-3 py-2 text-sm bg-gray-50 cursor-not-allowed" />
      </div>
      <p className="text-center text-xs text-indigo-400 mt-2 font-medium">
        Search Box — {collectionName || 'inherited collection'}
      </p>
    </div>
  );
};
