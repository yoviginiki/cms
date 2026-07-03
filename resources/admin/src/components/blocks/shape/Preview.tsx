import type { BlockComponentProps } from '@/types/blocks';

export const ShapePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const color = ((block.data as Record<string, unknown>).color as string) || '#E63B2E';

  return <div className="w-full h-full min-h-[24px]" style={{ background: color }} />;
};
