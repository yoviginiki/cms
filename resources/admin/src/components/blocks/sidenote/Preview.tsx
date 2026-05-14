import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

export const SidenotePreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const { content, side } = block.data as { content: string; side: string };

  return (
    <aside
      className={`text-sm text-base-content/60 max-w-[200px] p-2 border border-base-content/10 rounded ${
        side === 'left' ? 'float-left mr-4' : 'float-right ml-4'
      }`}
    >
      <div className="text-[10px] text-base-content/30 uppercase mb-1">
        Side Note ({side})
      </div>
      <InlineTextField
        as="span"
        value={content || ''}
        placeholder="Add side note..."
        onChange={(v) => onUpdate({ ...block.data, content: v })}
        className="block"
      />
    </aside>
  );
};
