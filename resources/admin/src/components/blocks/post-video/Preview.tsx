import type { BlockComponentProps } from '@/types/blocks';

export const PostVideoPreview: React.FC<BlockComponentProps> = () => (
  <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-6 bg-indigo-50/30">
    <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
    <div className="flex flex-col items-center justify-center py-10 opacity-50">
      <div className="relative w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center mb-3">
        <svg className="w-8 h-8 text-gray-400 ml-1" fill="currentColor" viewBox="0 0 24 24">
          <path d="M8 5v14l11-7z" />
        </svg>
      </div>
      <p className="text-xs text-indigo-400 font-medium">Post Video</p>
    </div>
  </div>
);
