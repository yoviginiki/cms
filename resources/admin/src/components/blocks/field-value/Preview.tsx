import type { BlockComponentProps } from '@/types/blocks';

export const FieldValuePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const field = (data.field as string) || 'field';
  const showLabel = !!data.showLabel;
  const label = (data.labelText as string) || field;

  const style: React.CSSProperties = {
    ...(data.fontSize ? { fontSize: data.fontSize as string } : {}),
    ...(data.textAlign ? { textAlign: data.textAlign as React.CSSProperties['textAlign'] } : {}),
  };

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium">Dynamic</div>
      <div className="text-sm text-gray-500" style={style}>
        {showLabel && <span className="font-medium text-gray-600">{label}: </span>}
        <span className="bg-gray-100 rounded px-1.5 py-0.5 text-gray-400">[{field}]</span>
      </div>
    </div>
  );
};
