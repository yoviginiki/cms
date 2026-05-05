import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface TestimonialItem {
  quote: string;
  author: string;
  role: string;
  avatar: string;
}

export const TestimonialPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: TestimonialItem[]; layout: string };
  const items = data.items || [];
  const isGrid = data.layout === 'grid';

  return (
    <div className={isGrid ? 'grid grid-cols-2 gap-4' : 'space-y-4'}>
      {items.map((item, i) => (
        <blockquote key={i} className="rounded-lg border border-gray-200 p-4">
          <p className="text-sm italic text-gray-700 mb-3">&ldquo;{item.quote}&rdquo;</p>
          <div className="flex items-center gap-2">
            {item.avatar && (
              <div className="w-8 h-8 rounded-full bg-gray-200 overflow-hidden">
                <img src={item.avatar} alt="" className="w-full h-full object-cover" />
              </div>
            )}
            <div>
              <div className="text-sm font-semibold">{item.author}</div>
              <div className="text-xs text-gray-500">{item.role}</div>
            </div>
          </div>
        </blockquote>
      ))}
    </div>
  );
};
