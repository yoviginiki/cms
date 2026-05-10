import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { buildBackgroundStyle, buildOverlayStyle } from '@/components/editor/BackgroundEditor';
import { InlineTextField } from '@/components/editor/fields';

// ── Safe CSS value helpers (preview-only; Blade has its own sanitizers) ──
const safeDim = (v: string) =>
  /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';
const safeColor = (v: string) =>
  /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';

// ── CTA default colors — aligned with Blade (hero.blade.php) ──
// no-bg filled: #333 bg, #fff text, #333 border
// with-bg filled: rgba(255,255,255,0.2) bg, #fff text, #fff border
const CTA_DEFAULTS = {
  filledBg:     (hasBg: boolean) => hasBg ? 'rgba(255,255,255,0.2)' : '#333',
  filledColor:  (_hasBg: boolean) => '#fff',
  filledBorder: (hasBg: boolean) => hasBg ? '#fff' : '#333',
  outlineBorder:(hasBg: boolean) => hasBg ? '#fff' : '#333',
  textColor:    (hasBg: boolean) => hasBg ? '#fff' : '#333',
};

export const HeroPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const title = (data.title as string) || '';
  const subtitle = (data.subtitle as string) || '';
  const ctaText = (data.ctaText as string) || '';
  const ctaUrl = (data.ctaUrl as string) || '';

  // Update helper — merges into existing block data
  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  // Configurable fields with sensible defaults matching previous hardcoded values
  const headlineTag = (data.headlineTag as string) || 'h1';
  const textAlignment = (data.textAlignment as string) || 'center';
  const verticalPosition = (data.verticalPosition as string) || 'center';
  const sectionHeight = (data.sectionHeight as string) || 'md';
  const contentMaxWidth = (data.contentMaxWidth as string) || '800px';
  const headlineSize = (data.headlineSize as string) || '2.5rem';
  const headlineWeight = (data.headlineWeight as string) || '700';
  const headlineColor = (data.headlineColor as string) || '';
  const subheadlineSize = (data.subheadlineSize as string) || '1.25rem';
  const adaptiveTextColor = data.adaptiveTextColor !== false;

  // CTA style fields
  const ctaVariant = (data.ctaVariant as string) || 'filled';
  const ctaSize = (data.ctaSize as string) || 'md';
  const ctaAlign = (data.ctaAlign as string) || '';
  const ctaBgColor = safeColor((data.ctaBgColor as string) || '');
  const ctaTextColor = safeColor((data.ctaTextColor as string) || '');
  const ctaBorderColor = safeColor((data.ctaBorderColor as string) || '');
  const ctaBorderWidth = safeDim((data.ctaBorderWidth as string) || '');
  const ctaBorderRadius = safeDim((data.ctaBorderRadius as string) || '');

  // Map sectionHeight to minHeight
  const heightMap: Record<string, number | string> = {
    auto: 'auto',
    sm: 300,
    md: 400,
    lg: 600,
    fullscreen: '100vh',
  };
  const minHeight = heightMap[sectionHeight] ?? 400;

  // Map verticalPosition to alignItems
  const alignMap: Record<string, string> = { top: 'flex-start', center: 'center', bottom: 'flex-end' };

  // Dynamic heading tag for InlineTextField
  const headingAs = headlineTag as 'h1' | 'h2' | 'h3';

  // ── Legacy fallback + normalize for BackgroundEditor ──
  // Old hero blocks saved backgroundImage; new ones use bg_image.
  // Normalize into effectiveData so buildBackgroundStyle works,
  // and so BackgroundEditor sees the image when bg_type=image.
  const legacyImage = (data.backgroundImage as string) || '';
  const currentBgImage = (data.bg_image as string) || '';
  const effectiveBgImage = currentBgImage || legacyImage;

  // Build effective data with legacy normalization
  const effectiveData = (!currentBgImage && legacyImage)
    ? { ...data, bg_type: 'image', bg_image: legacyImage }
    : data;

  const bgStyle = buildBackgroundStyle(effectiveData);
  const overlayStyle = buildOverlayStyle(effectiveData);

  // hasBg must check actual usable values, not just bg_type
  const effectiveBgType = (effectiveData.bg_type as string) || 'none';
  const gradientStops = data.bg_gradient_stops;
  const hasBg = (() => {
    switch (effectiveBgType) {
      case 'color':   return !!(data.bg_color as string);
      case 'gradient': return Array.isArray(gradientStops) && gradientStops.length > 0;
      case 'image':   return !!effectiveBgImage;
      default:        return false;
    }
  })();

  // Derive text colors
  const resolvedHeadlineColor = headlineColor
    || (adaptiveTextColor && hasBg ? 'white' : 'inherit');
  const resolvedSubtitleColor = adaptiveTextColor && hasBg
    ? 'rgba(255,255,255,0.85)'
    : 'inherit';

  // ── CTA button style computation ──
  const ctaSizeMap: Record<string, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-7 py-3.5 text-base',
  };
  const sizeClass = ctaSizeMap[ctaSize] || ctaSizeMap.md;

  // ── Fix #4: CTA defaults aligned with Blade ──
  const getDefaultCtaStyle = (): React.CSSProperties => {
    const style: React.CSSProperties = {};

    switch (ctaVariant) {
      case 'outline':
        style.backgroundColor = ctaBgColor || 'transparent';
        style.color = ctaTextColor || CTA_DEFAULTS.textColor(hasBg);
        style.borderStyle = 'solid';
        style.borderWidth = ctaBorderWidth || '2px';
        style.borderColor = ctaBorderColor || CTA_DEFAULTS.outlineBorder(hasBg);
        break;
      case 'ghost':
        style.backgroundColor = ctaBgColor || 'transparent';
        style.color = ctaTextColor || CTA_DEFAULTS.textColor(hasBg);
        style.borderStyle = 'none';
        break;
      case 'link':
        style.backgroundColor = 'transparent';
        style.color = ctaTextColor || CTA_DEFAULTS.textColor(hasBg);
        style.borderStyle = 'none';
        style.textDecoration = 'underline';
        break;
      case 'filled':
      default:
        style.backgroundColor = ctaBgColor || CTA_DEFAULTS.filledBg(hasBg);
        style.color = ctaTextColor || CTA_DEFAULTS.filledColor(hasBg);
        if (ctaBorderWidth || ctaBorderColor) {
          style.borderStyle = 'solid';
          style.borderWidth = ctaBorderWidth || '2px';
          style.borderColor = ctaBorderColor || CTA_DEFAULTS.filledBorder(hasBg);
        } else {
          style.borderStyle = 'solid';
          style.borderWidth = '2px';
          style.borderColor = CTA_DEFAULTS.filledBorder(hasBg);
        }
        break;
    }

    if (ctaBorderRadius) {
      style.borderRadius = ctaBorderRadius;
    }

    return style;
  };

  const ctaStyle = getDefaultCtaStyle();
  const effectiveCtaAlign = ctaAlign || textAlignment || 'center';

  const ctaAlignMap: Record<string, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };
  const ctaAlignClass = ctaAlignMap[effectiveCtaAlign] || 'text-center';

  // CTA visibility:
  //   - when selected: always show (author can type inline)
  //   - when not selected: show only when both ctaText AND ctaUrl exist (matches Blade)
  //   - partially configured (text but no URL): show only when selected
  const ctaIsPublishable = !!(ctaText && ctaUrl);
  const ctaIsPartial = !!(ctaText || ctaUrl) && !ctaIsPublishable;
  const showCtaEditor = ctaIsPublishable || isSelected;

  return (
    <div
      className={`relative rounded-lg overflow-hidden ${hasBg ? 'block-controls-own-colors' : ''}`}
      style={{
        minHeight,
        display: 'flex',
        alignItems: alignMap[verticalPosition] || 'center',
        justifyContent: 'center',
        ...bgStyle,
        ...(!hasBg ? { backgroundColor: 'oklch(var(--b3))' } : {}),
      }}
    >
      {overlayStyle && <div style={overlayStyle} />}

      <div
        className="relative z-10 px-6 py-8 w-full"
        style={{ textAlign: textAlignment as React.CSSProperties['textAlign'], maxWidth: contentMaxWidth }}
      >
        <InlineTextField
          as={headingAs}
          value={title}
          placeholder="Add hero title"
          onChange={(v) => update('title', v)}
          className={`mb-2 block ${hasBg ? 'drop-shadow-sm' : ''} ${!headlineColor && !adaptiveTextColor ? 'text-base-content' : ''}`}
          style={{
            fontSize: headlineSize,
            fontWeight: headlineWeight,
            color: resolvedHeadlineColor || undefined,
          }}
        />
        <InlineTextField
          as="p"
          value={subtitle}
          placeholder="Add subtitle"
          onChange={(v) => update('subtitle', v)}
          className={`mb-5 block ${hasBg ? 'drop-shadow-sm' : ''} ${!adaptiveTextColor && !hasBg ? 'text-base-content/70' : ''}`}
          style={{
            fontSize: subheadlineSize,
            color: resolvedSubtitleColor || undefined,
          }}
        />
        {/* CTA: visible when block is selected or CTA is partially configured.
             Blade only renders when both ctaText and ctaUrl are set.
             Show reduced opacity + dashed border when ctaUrl is missing. */}
        {showCtaEditor && (
          <div className={ctaAlignClass}>
            <InlineTextField
              as="span"
              value={ctaText}
              placeholder="Add button text"
              onChange={(v) => update('ctaText', v)}
              className={`inline-block font-semibold ${sizeClass} ${!ctaBorderRadius ? 'rounded-lg' : ''} ${hasBg && ctaVariant !== 'link' ? 'backdrop-blur-sm' : ''} ${!ctaIsPublishable ? 'opacity-60' : ''} ${ctaIsPartial && ctaVariant !== 'link' ? 'border-dashed' : ''} ${!ctaText && !ctaUrl ? 'opacity-30' : ''}`}
              style={{
                ...ctaStyle,
                ...((ctaIsPartial && ctaVariant !== 'link' && ctaVariant !== 'ghost') ? { borderStyle: 'dashed', borderWidth: ctaBorderWidth || '2px', borderColor: ctaBorderColor || (hasBg ? 'rgba(255,255,255,0.4)' : '#999') } : {}),
              }}
            />
            {ctaIsPartial && ctaText && !ctaUrl && (
              <span className="block text-xs mt-1 opacity-50">Add a URL in the side panel to publish this button</span>
            )}
            {ctaIsPartial && ctaUrl && !ctaText && (
              <span className="block text-xs mt-1 opacity-50">Add button text to publish this button</span>
            )}
            {!ctaText && !ctaUrl && (
              <span className="block text-[10px] mt-1 opacity-30">Editor only — not published</span>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
