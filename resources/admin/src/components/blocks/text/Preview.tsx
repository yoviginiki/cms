import type { BlockComponentProps } from '@/types/blocks';
import WysiwygEditor from '@/components/editor/WysiwygEditor';
import { resolveTextShadow } from '@/lib/blockStyles';

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) => /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const TextPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const content = (data.content as string) || '';

  // Typography controls — applied to inner content
  const textAlign = (data.textAlign as string) || '';
  const textColor = safeColor((data.textColor as string) || '');
  const fontSize = safeDim((data.fontSize as string) || '');
  const fontWeight = (data.fontWeight as string) || '';
  const fontStyle = (data.fontStyle as string) || '';
  const lineHeight = (data.lineHeight as string) || '';
  const letterSpacing = (data.letterSpacing as string) || '';
  const textShadow = resolveTextShadow(data.textShadow);

  const style: React.CSSProperties = {
    ...(textAlign ? { textAlign: textAlign as React.CSSProperties['textAlign'] } : {}),
    ...(textColor ? { color: textColor } : {}),
    ...(fontSize ? { fontSize } : {}),
    ...(fontWeight ? { fontWeight } : {}),
    ...(fontStyle === 'italic' ? { fontStyle: 'italic' } : {}),
    ...(lineHeight ? { lineHeight } : {}),
    ...(letterSpacing ? { letterSpacing } : {}),
    ...(textShadow ? { textShadow } : {}),
  };

  if (isSelected) {
    return (
      <div onClick={e => e.stopPropagation()} style={style}>
        <WysiwygEditor
          content={content}
          onChange={(html) => onUpdate({ content: html })}
          minHeight={100}
          placeholder="Type your text here..."
        />
      </div>
    );
  }

  if (!content) {
    return (
      <div className="prose max-w-none" style={style}>
        <p className="text-gray-400 italic">Click to add text...</p>
      </div>
    );
  }

  return <div className="prose max-w-none" style={style} dangerouslySetInnerHTML={{ __html: content }} />;
};
