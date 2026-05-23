import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';
import { resolveTextShadow, safeDim } from '@/lib/blockStyles';

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
  const sd = safeDim;

  if (sd(layout.width)) style.width = sd(layout.width);
  if (sd(layout.height)) style.height = sd(layout.height);
  if (sd(layout.minWidth)) style.minWidth = sd(layout.minWidth);
  if (sd(layout.minHeight)) style.minHeight = sd(layout.minHeight);
  if (sd(layout.maxWidth)) style.maxWidth = sd(layout.maxWidth);
  if (sd(layout.maxHeight)) style.maxHeight = sd(layout.maxHeight);
  if (layout.overflow && layout.overflow !== 'visible') style.overflow = layout.overflow as any;
  if (sd(spacing.paddingTop)) style.paddingTop = sd(spacing.paddingTop);
  if (sd(spacing.paddingBottom)) style.paddingBottom = sd(spacing.paddingBottom);
  if (sd(spacing.paddingLeft)) style.paddingLeft = sd(spacing.paddingLeft);
  if (sd(spacing.paddingRight)) style.paddingRight = sd(spacing.paddingRight);
  if (sd(spacing.marginTop)) style.marginTop = sd(spacing.marginTop);
  if (sd(spacing.marginBottom)) style.marginBottom = sd(spacing.marginBottom);
  if (sd(visual.borderRadius)) style.borderRadius = sd(visual.borderRadius);

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
