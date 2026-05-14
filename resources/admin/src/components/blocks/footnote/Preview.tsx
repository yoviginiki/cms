import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

export const FootnotePreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const { content, marker } = block.data as { content: string; marker: string };

  return (
    <aside className="text-sm text-base-content/60 border-t border-base-content/10 pt-2 mt-2">
      <sup className="text-base-content/80 font-bold">{marker || '*'}</sup>{' '}
      <InlineTextField
        as="span"
        value={content || ''}
        placeholder="Add footnote text..."
        onChange={(v) => onUpdate({ ...block.data, content: v })}
        className="inline"
        showCharacterCount
        recommendedLength={300}
      />
    </aside>
  );
};
