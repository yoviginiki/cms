import type { BlockComponentProps } from '@/types/blocks';
import WysiwygEditor from '@/components/editor/WysiwygEditor';

export const DropcapPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const { content, capSize, capColor } = block.data as {
    content: string;
    capSize: number;
    capColor: string | null;
  };

  const style = `
    .dropcap-preview::first-letter {
      float: left;
      font-size: ${capSize || 3}em;
      line-height: 0.8;
      padding-right: 0.1em;
      font-weight: bold;
      ${capColor ? `color: ${capColor};` : 'color: var(--color-text);'}
    }
  `;

  if (isSelected) {
    return (
      <>
        <style>{style}</style>
        <div className="dropcap-preview" onClick={e => e.stopPropagation()}>
          <WysiwygEditor
            content={content || ''}
            onChange={(html) => onUpdate({ ...block.data, content: html })}
            minHeight={80}
            placeholder="Type your drop cap text..."
          />
        </div>
      </>
    );
  }

  if (!content) {
    return (
      <div className="prose max-w-none">
        <p className="text-base-content/40 italic">Click to add drop cap text...</p>
      </div>
    );
  }

  return (
    <>
      <style>{style}</style>
      <div
        className="dropcap-preview prose max-w-none text-base-content/80"
        dangerouslySetInnerHTML={{ __html: content }}
      />
    </>
  );
};
