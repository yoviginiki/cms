import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';
import { resolveTextShadow } from '@/lib/blockStyles';

const sizeClassMap: Record<string, string> = {
  h1: 'text-4xl', h2: 'text-3xl', h3: 'text-2xl', h4: 'text-xl', h5: 'text-lg', h6: 'text-base',
};

export const HeadingPreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const text = (data.text as string) || '';
  const level = (data.level as string) || 'h2';
  const tag = (['h1','h2','h3','h4','h5','h6'].includes(level) ? level : 'h2') as 'h1'|'h2'|'h3'|'h4'|'h5'|'h6';

  // Typography overrides
  const color = (data.color as string) || '';
  const fontSize = (data.fontSize as string) || '';
  const fontWeight = (data.fontWeight as string) || '';
  const lineHeight = (data.lineHeight as string) || '';
  const letterSpacing = (data.letterSpacing as string) || '';
  const textTransform = (data.textTransform as string) || '';
  const textAlign = (data.textAlign as string) || '';
  const textShadow = resolveTextShadow(data.textShadow);

  const hasCustomStyle = color || fontSize || fontWeight || lineHeight || letterSpacing || textTransform || textAlign || textShadow;

  const style: React.CSSProperties = {
    ...(fontSize ? { fontSize } : {}),
    ...(fontWeight ? { fontWeight } : {}),
    ...(color ? { color } : {}),
    ...(lineHeight ? { lineHeight } : {}),
    ...(letterSpacing ? { letterSpacing } : {}),
    ...(textTransform ? { textTransform: textTransform as React.CSSProperties['textTransform'] } : {}),
    ...(textAlign ? { textAlign: textAlign as React.CSSProperties['textAlign'] } : {}),
    ...(textShadow ? { textShadow } : {}),
  };

  // Use Tailwind size class only when no custom fontSize is set
  const sizeClass = fontSize ? '' : (sizeClassMap[tag] || sizeClassMap.h2);

  return (
    <InlineTextField
      as={tag}
      value={text}
      placeholder="Add heading"
      onChange={(v) => onUpdate({ ...block.data, text: v })}
      className={`${sizeClass} font-bold block`}
      style={hasCustomStyle ? style : undefined}
    />
  );
};
