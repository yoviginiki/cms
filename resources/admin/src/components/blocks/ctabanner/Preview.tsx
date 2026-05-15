import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) => /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const CtabannerPreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: string) => onUpdate({ ...block.data, [field]: value });

  const bgColor = (data.backgroundColor as string) || '#3b82f6';
  const bgStyle: React.CSSProperties =
    (data.backgroundStyle as string) === 'gradient'
      ? { background: `linear-gradient(135deg, ${bgColor}, ${bgColor}cc)` }
      : (data.backgroundStyle as string) === 'image' && (data.backgroundImage as string)
        ? { backgroundImage: `url(${data.backgroundImage})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { backgroundColor: bgColor };

  const headingColor = safeColor((data.headingColor as string) || '');
  const textColor = safeColor((data.textColor as string) || '');
  const btnBgColor = safeColor((data.btnBgColor as string) || '');
  const btnTextColor = safeColor((data.btnTextColor as string) || '');
  const btnBorderRadius = safeDim((data.btnBorderRadius as string) || '');

  return (
    <div className="rounded-lg p-6 text-center text-white" style={{ ...bgStyle, minHeight: 80 }}>
      <InlineTextField as="h3" value={(data.heading as string) || ''} placeholder="Add heading" onChange={(v) => update('heading', v)}
        className="text-lg font-bold mb-1 block" style={{ color: headingColor || undefined }} />
      <InlineTextField as="p" value={(data.text as string) || ''} placeholder="Add description..." onChange={(v) => update('text', v)}
        multiline className="text-sm opacity-90 mb-3 block" style={{ color: textColor || undefined }} />
      <InlineTextField as="span" value={(data.buttonText as string) || ''} placeholder="Button text" onChange={(v) => update('buttonText', v)}
        className="inline-block px-4 py-1.5 rounded text-sm font-medium"
        style={{
          backgroundColor: btnBgColor || 'rgba(255,255,255,0.2)',
          color: btnTextColor || 'inherit',
          borderRadius: btnBorderRadius || '0.25rem',
        }} />
      {(data.buttonUrl as string) && (data.buttonUrl as string) !== '#' && (
        <span className="block mt-1 text-xs opacity-60">{data.buttonUrl as string}</span>
      )}
    </div>
  );
};
