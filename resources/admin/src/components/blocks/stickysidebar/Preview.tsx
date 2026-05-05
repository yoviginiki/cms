import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const StickySidebarPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    sidebarSide: string;
    sidebarWidth: string;
    gap: string;
    stickyOffset: string;
  };

  const isLeft = data.sidebarSide === 'left';

  const mainPanel = (
    <div className="flex-1 border-2 border-dashed border-gray-300 rounded-lg p-4 min-h-[100px] bg-gray-50 flex items-center justify-center text-gray-400 text-xs">
      Main Content
    </div>
  );

  const sidebarPanel = (
    <div
      className="border-2 border-dashed border-blue-300 rounded-lg p-4 min-h-[100px] bg-blue-50 flex flex-col items-center justify-center text-blue-400 text-xs"
      style={{ width: '120px', flexShrink: 0 }}
    >
      <div>Sidebar</div>
      <div className="mt-1 text-[10px] opacity-60">sticky</div>
    </div>
  );

  return (
    <div className="flex gap-3 w-full">
      {isLeft ? sidebarPanel : mainPanel}
      {isLeft ? mainPanel : sidebarPanel}
    </div>
  );
};
