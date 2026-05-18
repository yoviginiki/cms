import type { BlockComponentProps } from '@/types/blocks';

export const PostMetaPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const sep = (data.separator as string) || '·';
  const parts: string[] = [];
  if (data.showDate !== false) parts.push('Jan 15, 2026');
  if (data.showAuthor !== false) parts.push('John Doe');
  if (data.showCategory !== false) parts.push('Category Name');

  return (
    <div className="relative border-2 border-dashed border-indigo-200 rounded-lg p-4 bg-indigo-50/30">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <p className="text-sm text-gray-500 text-center">{parts.join(` ${sep} `) || 'No meta fields selected'}</p>
    </div>
  );
};
