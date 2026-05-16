import type { BlockComponentProps } from '@/types/blocks';
import { buildShadowCss } from '@/lib/shadowStyles';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { resolveCornerRadius } from '@/lib/spacingHelpers';

interface TestimonialItem { quote: string; author: string; role: string; avatar: string }

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';

export const TestimonialPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const items = (data.items as TestimonialItem[]) || [];
  const isGrid = (data.layout as string) === 'grid';

  const cardBgColor = safeColor((data.cardBgColor as string) || '');
  const cardBorderColor = safeColor((data.cardBorderColor as string) || '') || 'var(--color-border,#e2e8f0)';
  const cardBorderRadius = resolveCornerRadius(data.cardBorderRadius, '0.5rem');
  const cardShadowCss = buildShadowCss((data.cardShadowMode as string) || 'preset', (data.cardShadow as string) || '', (data.cardShadowCustom as ShadowCustom) || {});
  const quoteColor = safeColor((data.quoteColor as string) || '');
  const authorColor = safeColor((data.authorColor as string) || '');

  const cardStyle: React.CSSProperties = {
    padding: '1rem', border: `1px solid ${cardBorderColor}`, borderRadius: cardBorderRadius,
    ...(cardBgColor ? { backgroundColor: cardBgColor } : {}),
    ...(cardShadowCss ? { boxShadow: cardShadowCss } : {}),
  };

  return (
    <div className={isGrid ? 'grid grid-cols-2 gap-4' : 'space-y-4'}>
      {(items || []).map((item, i) => (
        <blockquote key={i} style={cardStyle}>
          <p className="text-sm italic mb-3" style={{ color: quoteColor || 'var(--color-text,#1e293b)' }}>&ldquo;{item.quote}&rdquo;</p>
          <div className="flex items-center gap-2">
            {item.avatar && (
              <div className="w-8 h-8 rounded-full overflow-hidden" style={{ background: 'var(--color-bg-alt,#f8fafc)' }}>
                <img src={item.avatar} alt="" className="w-full h-full object-cover" />
              </div>
            )}
            <div>
              <div className="text-sm font-semibold" style={{ color: authorColor || undefined }}>{item.author}</div>
              <div className="text-xs" style={{ color: 'var(--color-text-muted,#64748b)' }}>{item.role}</div>
            </div>
          </div>
        </blockquote>
      ))}
    </div>
  );
};
