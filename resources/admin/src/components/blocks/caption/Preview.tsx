import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

export const CaptionPreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const { text, prefix } = block.data as { text: string; prefix: string };

  return (
    <figcaption className="text-sm text-base-content/50">
      {prefix && <span className="text-base-content/40">{prefix} </span>}
      <InlineTextField
        as="span"
        value={text || ''}
        placeholder="Add caption text..."
        onChange={(v) => onUpdate({ ...block.data, text: v })}
        className="inline"
        showCharacterCount
        recommendedLength={200}
      />
    </figcaption>
  );
};
