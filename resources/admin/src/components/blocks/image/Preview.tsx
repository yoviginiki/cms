import type { BlockComponentProps } from '@/types/blocks';
import { InlineMediaReplace } from '@/components/editor/fields';
import { buildShadowCss } from '@/lib/shadowStyles';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { resolveCornerRadius } from '@/lib/spacingHelpers';

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) => /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const ImagePreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const url = (data.url as string) || '';
  const alt = (data.alt as string) || '';
  const caption = (data.caption as string) || '';

  const borderRadius = resolveCornerRadius(data.borderRadius);
  const shadowCss = buildShadowCss((data.shadowMode as string) || 'preset', (data.shadow as string) || '', (data.shadowCustom as ShadowCustom) || {});
  const borderColor = safeColor((data.borderColor as string) || '');
  const borderWidth = safeDim((data.borderWidth as string) || '');

  const handleImageChange = (newUrl: string, assetId?: string) => {
    onUpdate({ ...block.data, url: newUrl, ...(assetId ? { assetId } : {}) });
  };

  const imgStyle: React.CSSProperties = {
    width: '100%',
    ...(borderRadius ? { borderRadius } : { borderRadius: '0.5rem' }),
    ...(shadowCss ? { boxShadow: shadowCss } : {}),
    ...(borderWidth && borderColor ? { border: `${borderWidth} solid ${borderColor}` } : {}),
  };

  if (!url) {
    return (
      <div className="relative bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-12 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <svg className="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p className="text-sm">No image selected</p>
        </div>
        <InlineMediaReplace value="" onChange={handleImageChange} accept="image" label="image" overlay />
      </div>
    );
  }

  return (
    <figure className="relative">
      <img src={url} alt={alt} style={imgStyle} />
      {isSelected && <InlineMediaReplace value={url} onChange={handleImageChange} accept="image" label="image" overlay />}
      {caption && <figcaption className="text-sm mt-2 text-center" style={{ color: 'var(--color-text-muted,#64748b)' }}>{caption}</figcaption>}
    </figure>
  );
};
