import type { BlockComponentProps } from '@/types/blocks';
import { useSiteQueries } from '../collections-shared';

export const QueryTablePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const { data: queries } = useSiteQueries();
  const query = queries?.find((q) => q.id === data.queryId);
  const striped = data.striped !== false;

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <div className="text-xs text-gray-500 mb-1">{query ? `Query: ${query.name}` : 'Pick a saved query'}</div>
      <table className="w-full text-sm border-collapse">
        {data.showHeader !== false && (
          <thead>
            <tr className="border-b border-gray-300 text-left">
              <th className="py-1 pr-4 font-medium">Title</th>
              <th className="py-1 pr-4 font-medium">Value</th>
            </tr>
          </thead>
        )}
        <tbody>
          {[1, 2, 3].map((i) => (
            <tr key={i} className={`border-b border-gray-200 ${striped && i % 2 === 0 ? 'bg-gray-50' : ''}`}>
              <td className="py-1 pr-4 text-gray-600">Sample row {i}</td>
              <td className="py-1 pr-4 text-gray-500 tabular-nums">…</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
