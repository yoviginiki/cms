import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';
import { resolveTextShadow, safeDim } from '@/lib/blockStyles';

// Same defaults as resources/views/blocks/heading.blade.php — theme size tokens
// (defined on the canvas / published page), with the publish fallbacks.
const sizeVarMap: Record<string, string> = {
  h1: 'var(--font-size-3xl,2rem)', h2: 'var(--font-size-2xl,1.5rem)', h3: 'var(--font-size-xl,1.25rem)',
  h4: 'var(--font-size-lg,1.125rem)', h5: 'var(--font-size-base,1rem)', h6: 'var(--font-size-sm,0.875rem)',
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
    fontSize: fontSize || sizeVarMap[tag] || sizeVarMap.h2,
    fontWeight: fontWeight || 'var(--heading-weight,var(--font-weight-bold,700))',
    fontFamily: 'var(--font-heading,inherit)',
    lineHeight: 'var(--line-height-heading,var(--line-height-tight,1.25))',
    margin: '0 0 var(--heading-margin-bottom,0.4em)',
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


  return (
    <InlineTextField
      as={tag}
      value={text}
      placeholder="Add heading"
      onChange={(v) => onUpdate({ ...block.data, text: v })}
      className="block"
      style={style}
    />
  );
};
