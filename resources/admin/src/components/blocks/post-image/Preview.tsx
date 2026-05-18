import type { BlockComponentProps } from '@/types/blocks';

export const PostImagePreview: React.FC<BlockComponentProps> = () => (
  <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-6 bg-indigo-50/30">
    <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
    <div className="flex flex-col items-center justify-center py-8 opacity-50">
      <svg className="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <rect x="3" y="3" width="18" height="18" rx="2" strokeWidth="2" />
        <circle cx="8.5" cy="8.5" r="1.5" strokeWidth="2" />
        <path d="M21 15l-5-5L5 21" strokeWidth="2" />
      </svg>
      <p className="text-xs text-indigo-400 font-medium">Featured Image</p>
    </div>
  </div>
);
