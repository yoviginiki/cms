import type { BlockComponentProps } from '@/types/blocks';

export const PostContentPreview: React.FC<BlockComponentProps> = () => (
  <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-6 bg-indigo-50/30">
    <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
    <div className="space-y-3 opacity-50">
      <div className="h-4 bg-gray-200 rounded w-full" />
      <div className="h-4 bg-gray-200 rounded w-5/6" />
      <div className="h-4 bg-gray-200 rounded w-4/6" />
      <div className="h-32 bg-gray-100 rounded w-full mt-4" />
      <div className="h-4 bg-gray-200 rounded w-full" />
      <div className="h-4 bg-gray-200 rounded w-3/4" />
    </div>
    <p className="text-center text-xs text-indigo-400 mt-3 font-medium">Post Content Area</p>
  </div>
);
