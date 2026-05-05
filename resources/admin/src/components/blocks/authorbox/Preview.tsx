import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const AuthorboxPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { showAvatar: boolean; showBio: boolean; showSocialLinks: boolean; layout: string };
  const isVertical = data.layout === 'vertical';

  return (
    <div className={`rounded-lg border border-gray-200 p-4 ${isVertical ? 'text-center' : 'flex items-start gap-4'}`}>
      {data.showAvatar && (
        <div className={`bg-gray-200 rounded-full flex-shrink-0 ${isVertical ? 'w-16 h-16 mx-auto mb-2' : 'w-14 h-14'}`} />
      )}
      <div>
        <div className="font-semibold text-sm">Author Name</div>
        {data.showBio && <p className="text-xs text-gray-500 mt-1">Author bio placeholder text goes here...</p>}
        {data.showSocialLinks && (
          <div className={`flex gap-2 mt-2 ${isVertical ? 'justify-center' : ''}`}>
            <span className="text-xs text-blue-600">Twitter</span>
            <span className="text-xs text-blue-600">LinkedIn</span>
          </div>
        )}
      </div>
    </div>
  );
};
