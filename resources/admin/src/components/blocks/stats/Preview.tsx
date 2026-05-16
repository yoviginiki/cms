import type { BlockComponentProps } from '@/types/blocks';
import { buildShadowCss } from '@/lib/shadowStyles';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { resolveCornerRadius } from '@/lib/spacingHelpers';

interface StatItem { value: string; label: string; prefix: string; suffix: string }

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) => /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const StatsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as StatItem[]) || [];
  const cols = Number(data.columns) || 3;
  const gap = safeDim((data.gap as string) || '') || '1.5rem';

  const cardBgColor = safeColor((data.cardBgColor as string) || '');
  const cardBorderColor = safeColor((data.cardBorderColor as string) || '') || 'var(--color-border,#e2e8f0)';
  const cardBorderRadius = resolveCornerRadius(data.cardBorderRadius, '0.5rem');
  const cardShadowCss = buildShadowCss((data.cardShadowMode as string) || 'preset', (data.cardShadow as string) || '', (data.cardShadowCustom as ShadowCustom) || {});

  const valueColor = safeColor((data.valueColor as string) || '');
  const labelColor = safeColor((data.labelColor as string) || '') || 'var(--color-text-muted,#64748b)';
  const valueFontSize = safeDim((data.valueFontSize as string) || '') || '1.5rem';

  const cardStyle: React.CSSProperties = {
    textAlign: 'center', padding: '1.5rem',
    border: `1px solid ${cardBorderColor}`, borderRadius: cardBorderRadius,
    ...(cardBgColor ? { backgroundColor: cardBgColor } : {}),
    ...(cardShadowCss ? { boxShadow: cardShadowCss } : {}),
  };

  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)`, gap }}>
      {(items || []).map((item, i) => (
        <div key={i} style={cardStyle}>
          <div style={{ fontSize: valueFontSize, fontWeight: 700, ...(valueColor ? { color: valueColor } : {}) }}>
            {item.prefix}{item.value}{item.suffix}
          </div>
          <div style={{ fontSize: '0.8125rem', marginTop: '0.25rem', color: labelColor }}>{item.label}</div>
        </div>
      ))}
    </div>
  );
};
