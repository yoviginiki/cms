import type { BlockEditorProps } from '@/types/blocks';
import { TokenColorInput } from '@/components/editor/fields/TokenColorInput';

export const ShapeEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const color = ((block.data as Record<string, unknown>).color as string) || '';

  return (
    <div className="space-y-2">
      <TokenColorInput label="Fill color" value={color}
        onChange={v => onUpdate({ ...block.data, color: v || undefined })} />
      <p className="text-[10px] text-base-content/40">
        Size and position come from the Transform panel (inside a slide).
      </p>
    </div>
  );
};
