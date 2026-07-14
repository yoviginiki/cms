import type { BlockComponentProps } from '@/types/blocks';

export const RecordTitlePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const tag = (data.tag as string) || 'h1';
  const Tag = (['h1','h2','h3','h4','h5','h6'].includes(tag) ? tag : 'h1') as 'h1';

  const style: React.CSSProperties = {
    ...(data.fontSize ? { fontSize: data.fontSize as string } : {}),
    ...(data.color ? { color: data.color as string } : {}),
    ...(data.textAlign ? { textAlign: data.textAlign as React.CSSProperties['textAlign'] } : {}),
  };

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <Tag className="text-3xl font-bold" style={style}>
        Record Title
      </Tag>
    </div>
  );
};
