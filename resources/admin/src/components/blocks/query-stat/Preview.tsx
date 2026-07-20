import type { BlockComponentProps } from '@/types/blocks';
import { useSiteQueries } from '../collections-shared';

const SIZES: Record<string, string> = { sm: 'text-xl', md: 'text-3xl', lg: 'text-5xl', xl: 'text-7xl' };

export const QueryStatPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const { data: queries } = useSiteQueries();
  const query = queries?.find((q) => q.id === data.queryId);

  const align = (data.textAlign as string) || 'left';

  return (
    <div className="relative" style={{ textAlign: align as React.CSSProperties['textAlign'] }}>
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <div className={`${SIZES[(data.size as string) || 'lg'] ?? SIZES.lg} font-semibold tabular-nums`}>
        {(data.prefix as string) || ''}1 234{(data.suffix as string) || ''}
      </div>
      <div className="text-sm text-gray-500 mt-1">
        {(data.label as string) || (query ? query.name : 'Pick a saved query')}
      </div>
    </div>
  );
};
