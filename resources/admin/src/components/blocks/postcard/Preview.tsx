import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const PostcardPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { postId: string; style: string; showExcerpt: boolean; showDate: boolean; showCategory: boolean };
  const isHorizontal = data.style === 'horizontal';

  return (
    <div className={`rounded-lg border border-gray-200 overflow-hidden ${isHorizontal ? 'flex' : ''}`}>
      <div className={`bg-gray-100 ${isHorizontal ? 'w-1/3 min-h-[120px]' : 'h-32'}`} />
      <div className="p-4 flex-1">
        {data.showCategory && <div className="text-[10px] text-blue-600 font-medium mb-1">Category</div>}
        <div className="text-sm font-semibold mb-1">Post Title {data.postId ? `#${data.postId}` : '(no ID)'}</div>
        {data.showDate && <div className="text-[10px] text-gray-400 mb-1">Jan 1, 2024</div>}
        {data.showExcerpt && <div className="text-xs text-gray-500">Post excerpt placeholder text...</div>}
      </div>
    </div>
  );
};
