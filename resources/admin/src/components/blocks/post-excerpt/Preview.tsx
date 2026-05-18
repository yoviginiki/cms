import type { BlockComponentProps } from '@/types/blocks';

export const PostExcerptPreview: React.FC<BlockComponentProps> = () => (
  <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-4 bg-indigo-50/30">
    <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
    <p className="text-sm text-gray-400 leading-relaxed">
      This is a preview of the post excerpt text. It provides a brief summary of the article content
      and is resolved dynamically from the post data at render time...
    </p>
  </div>
);
