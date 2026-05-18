import type { BlockComponentProps } from '@/types/blocks';

export const PostNavigationPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const style = (data.style as string) || 'minimal';
  const showLabels = data.showLabels !== false;

  const isButtons = style === 'buttons';

  return (
    <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-4 bg-indigo-50/30">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <div className="flex items-center justify-between text-sm text-gray-400">
        <span className={isButtons ? 'px-3 py-1.5 border border-gray-200 rounded' : ''}>
          {showLabels && <span className="text-[10px] text-gray-300 block">Previous</span>}
          &larr; Previous Post
        </span>
        <span className="text-gray-200">|</span>
        <span className={`text-right ${isButtons ? 'px-3 py-1.5 border border-gray-200 rounded' : ''}`}>
          {showLabels && <span className="text-[10px] text-gray-300 block">Next</span>}
          Next Post &rarr;
        </span>
      </div>
    </div>
  );
};
