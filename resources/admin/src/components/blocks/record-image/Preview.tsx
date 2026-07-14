import { Image as ImageIcon } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';

const RATIO_MAP: Record<string, string> = { '16:9': '16 / 9', '4:3': '4 / 3', '1:1': '1 / 1', '3:2': '3 / 2' };

export const RecordImagePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const field = (data.field as string) || '';
  const ratio = RATIO_MAP[(data.aspectRatio as string) || ''];

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium z-10">Dynamic</div>
      <div className="bg-gray-200 rounded flex flex-col items-center justify-center gap-1.5 text-gray-400"
        style={ratio ? { aspectRatio: ratio } : { minHeight: 140 }}>
        <ImageIcon size={26} />
        <span className="text-[11px] font-medium">{field ? `Record image — ${field}` : 'Record image — first image field'}</span>
      </div>
    </div>
  );
};
