import type { BlockComponentProps } from '@/types/blocks';
import { buildShadowCss } from '@/lib/shadowStyles';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { resolveCornerRadius } from '@/lib/spacingHelpers';

interface FeatureItem { icon: string; title: string; description: string }

const safeColor = (v: string) =>
  /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) =>
  /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const FeaturegridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as FeatureItem[]) || [];
  const cols = Number(data.columns) || 3;
  const style = (data.style as string) || 'icon-top';
  const gap = safeDim((data.gap as string) || '') || '1.5rem';

  // Card styling
  const cardBgColor = safeColor((data.cardBgColor as string) || '');
  const cardBorderColor = safeColor((data.cardBorderColor as string) || '') || 'var(--color-border,#e2e8f0)';
  const cardBorderWidth = safeDim((data.cardBorderWidth as string) || '') || '1px';
  const cardBorderRadius = resolveCornerRadius(data.cardBorderRadius, '0.5rem');
  const cardPadding = safeDim((data.cardPadding as string) || '') || '1.5rem';
  const cardShadowCss = buildShadowCss(
    (data.cardShadowMode as string) || 'preset',
    (data.cardShadow as string) || '',
    (data.cardShadowCustom as ShadowCustom) || {},
  );

  // Typography
  const titleColor = safeColor((data.titleColor as string) || '');
  const descColor = safeColor((data.descColor as string) || '');
  const iconSize = safeDim((data.iconSize as string) || '') || '2rem';
  const iconColor = safeColor((data.iconColor as string) || '');

  const cardStyle: React.CSSProperties = {
    display: 'flex',
    flexDirection: style === 'icon-left' ? 'row' : 'column',
    alignItems: style === 'icon-left' ? 'flex-start' : 'center',
    textAlign: style === 'icon-left' ? 'left' : 'center',
    gap: '0.75rem',
    padding: cardPadding,
    border: `${cardBorderWidth} solid ${cardBorderColor}`,
    borderRadius: cardBorderRadius,
    ...(cardBgColor ? { backgroundColor: cardBgColor } : {}),
    ...(cardShadowCss ? { boxShadow: cardShadowCss } : {}),
  };

  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)`, gap }}>
      {items.map((item, i) => (
        <div key={i} style={cardStyle}>
          <div style={{ fontSize: iconSize, lineHeight: 1, ...(iconColor ? { color: iconColor } : {}) }}>{item.icon}</div>
          <div>
            <div style={{ fontWeight: 600, fontSize: '0.875rem', marginBottom: '0.25rem', ...(titleColor ? { color: titleColor } : {}) }}>{item.title}</div>
            <div style={{ fontSize: '0.8125rem', ...(descColor ? { color: descColor } : { color: 'var(--color-text-muted,#64748b)' }) }}>{item.description}</div>
          </div>
        </div>
      ))}
    </div>
  );
};
