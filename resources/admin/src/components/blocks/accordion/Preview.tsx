import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { resolveTextShadow } from '@/lib/blockStyles';

interface AccordionItem {
  title: string;
  content: string;
}

export const AccordionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: AccordionItem[]; titleTextShadow?: string };
  const items = data.items || [];
  const titleTextShadow = resolveTextShadow(data.titleTextShadow);

  return (
    <div className="rounded border border-gray-200 divide-y divide-gray-200">
      {(items || []).map((item, index) => (
        <div key={index} className="p-3">
          <div className="flex items-center justify-between">
            <span className="font-medium text-sm" style={titleTextShadow ? { textShadow: titleTextShadow } : undefined}>{item.title || 'Untitled'}</span>
            <span className="text-gray-400 text-xs">&#9660;</span>
          </div>
          {index === 0 && (
            <div
              className="mt-2 text-sm text-gray-600"
              dangerouslySetInnerHTML={{ __html: item.content }}
            />
          )}
        </div>
      ))}
    </div>
  );
};
