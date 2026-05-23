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

  // Apply shared style properties from panels (spacing, layout, visual)
  const blockStyle = block.style || {};
  const layout = (blockStyle as any).layout || {};
  const spacing = (blockStyle as any).spacing || {};
  const visual = (blockStyle as any).visual || {};

  if (layout.width) style.width = layout.width;
  if (layout.height) style.height = layout.height;
  if (layout.minWidth) style.minWidth = layout.minWidth;
  if (layout.minHeight) style.minHeight = layout.minHeight;
  if (layout.maxWidth) style.maxWidth = layout.maxWidth;
  if (layout.maxHeight) style.maxHeight = layout.maxHeight;
  if (layout.overflow) style.overflow = layout.overflow as any;
  if (spacing.paddingTop) style.paddingTop = spacing.paddingTop;
  if (spacing.paddingBottom) style.paddingBottom = spacing.paddingBottom;
  if (spacing.paddingLeft) style.paddingLeft = spacing.paddingLeft;
  if (spacing.paddingRight) style.paddingRight = spacing.paddingRight;
  if (spacing.marginTop) style.marginTop = spacing.marginTop;
  if (spacing.marginBottom) style.marginBottom = spacing.marginBottom;
  if (visual.borderRadius) style.borderRadius = visual.borderRadius;
  if (visual.shadow) style.boxShadow = visual.shadow;

  // Use Tailwind size class only when no custom fontSize is set
  const sizeClass = fontSize ? '' : (sizeClassMap[tag] || sizeClassMap.h2);

  return (
    <InlineTextField
      as={tag}
      value={text}
      placeholder="Add heading"
      onChange={(v) => onUpdate({ ...block.data, text: v })}
      className={`${sizeClass} font-bold block`}
      style={style}
    />
  );
};
