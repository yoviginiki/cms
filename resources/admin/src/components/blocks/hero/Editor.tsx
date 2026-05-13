import React, { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { TextField, SelectField, ToggleField, ColorField, ShadowField, BoxSpacingField, CornerRadiusField } from '@/components/editor/fields';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { ResponsiveField } from '@/components/editor/fields/ResponsiveField';
import type { Breakpoint } from '@/lib/responsiveValues';
import { getResponsiveValue, setResponsiveValue, clearResponsiveValue } from '@/lib/responsiveValues';

export const HeroEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const [responsiveBp, setResponsiveBp] = useState<Breakpoint>('desktop');

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  // Responsive-aware update: writes to the correct breakpoint level
  const updateResponsive = (key: string, value: unknown) => {
    onUpdate(setResponsiveValue(data, key, responsiveBp, value));
  };

  // Clear override for tablet/mobile
  const clearOverride = (key: string) => (bp: 'tablet' | 'mobile') => {
    onUpdate(clearResponsiveValue(data, key, bp));
  };

  // Get effective value for current breakpoint
  const rv = (key: string) => getResponsiveValue(data, key, responsiveBp) as string;

  // Normalize a value that may be a legacy string or a per-side/per-corner object.
  // If it's a string, return an empty object so the field renders correctly;
  // the string fallback is handled by Preview/Blade resolvers.
  const asObj = (val: unknown): Record<string, string> =>
    (typeof val === 'object' && val !== null) ? val as Record<string, string> : {};

  return (
    <div className="space-y-3">

      {/* ── Content Fields ── */}
      <TextField
        label="Title"
        value={(data.title as string) || ''}
        onChange={(v) => update('title', v)}
        placeholder="Add hero title"
        helperText="Main heading displayed prominently in the hero"
      />
      <TextField
        label="Subtitle"
        value={(data.subtitle as string) || ''}
        onChange={(v) => update('subtitle', v)}
        placeholder="Add subtitle or tagline"
      />

      {/* ── Layout — Whole Hero Section ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Layout — Whole Hero Section</div>
      <SelectField
        label="Heading Tag"
        value={(data.headlineTag as string) || 'h1'}
        onChange={(v) => update('headlineTag', v)}
        options={[
          { value: 'h1', label: 'H1' },
          { value: 'h2', label: 'H2' },
          { value: 'h3', label: 'H3' },
        ]}
        helperText="Use only one H1 per page. Use H2/H3 for additional Hero sections to avoid SEO penalties."
      />

      <ResponsiveField
        data={data}
        dataKey="textAlignment"
        label="Text Alignment"
        breakpoint={responsiveBp}
        onBreakpointChange={setResponsiveBp}
        onClearOverride={clearOverride('textAlignment')}
      >
        <SelectField
          label=""
          value={rv('textAlignment') || 'center'}
          onChange={(v) => updateResponsive('textAlignment', v)}
          options={[
            { value: 'left', label: 'Left' },
            { value: 'center', label: 'Center' },
            { value: 'right', label: 'Right' },
          ]}
        />
      </ResponsiveField>

      <SelectField
        label="Vertical Position"
        value={(data.verticalPosition as string) || 'center'}
        onChange={(v) => update('verticalPosition', v)}
        options={[
          { value: 'top', label: 'Top' },
          { value: 'center', label: 'Center' },
          { value: 'bottom', label: 'Bottom' },
        ]}
      />

      <ResponsiveField
        data={data}
        dataKey="sectionHeight"
        label="Section Height"
        breakpoint={responsiveBp}
        onBreakpointChange={setResponsiveBp}
        onClearOverride={clearOverride('sectionHeight')}
      >
        <SelectField
          label=""
          value={rv('sectionHeight') || 'md'}
          onChange={(v) => updateResponsive('sectionHeight', v)}
          options={[
            { value: 'auto', label: 'Auto' },
            { value: 'sm', label: 'Small (300px)' },
            { value: 'md', label: 'Medium (400px)' },
            { value: 'lg', label: 'Large (600px)' },
            { value: 'fullscreen', label: 'Full Screen' },
          ]}
        />
      </ResponsiveField>

      <ResponsiveField
        data={data}
        dataKey="contentMaxWidth"
        label="Content Max Width"
        breakpoint={responsiveBp}
        onBreakpointChange={setResponsiveBp}
        onClearOverride={clearOverride('contentMaxWidth')}
      >
        <TextField
          label=""
          value={rv('contentMaxWidth') || '800px'}
          onChange={(v) => updateResponsive('contentMaxWidth', v)}
          placeholder="e.g. 800px, 60rem"
        />
      </ResponsiveField>

      {/* ── Background — Whole Hero Section ── */}
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      {/* ── Typography — Title & Subtitle ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography — Title & Subtitle</div>
      <ResponsiveField
        data={data}
        dataKey="headlineSize"
        label="Title Size"
        breakpoint={responsiveBp}
        onBreakpointChange={setResponsiveBp}
        onClearOverride={clearOverride('headlineSize')}
      >
        <TextField
          label=""
          value={rv('headlineSize') || '2.5rem'}
          onChange={(v) => updateResponsive('headlineSize', v)}
          placeholder="e.g. 2.5rem, 48px"
          helperText="Font size for the title heading"
        />
      </ResponsiveField>
      <SelectField
        label="Title Weight"
        value={(data.headlineWeight as string) || '700'}
        onChange={(v) => update('headlineWeight', v)}
        options={[
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
          { value: '800', label: 'Extra Bold (800)' },
          { value: '900', label: 'Black (900)' },
        ]}
      />
      <ColorField
        label="Title Color"
        value={(data.headlineColor as string) || ''}
        onChange={(v) => update('headlineColor', v)}
      />
      <TextField
        label="Title Line Height"
        value={(data.headlineLineHeight as string) || ''}
        onChange={(v) => update('headlineLineHeight', v)}
        placeholder="e.g. 1.2, 1.5, 48px"
        helperText="Leave empty for default"
      />
      <TextField
        label="Title Letter Spacing"
        value={(data.headlineLetterSpacing as string) || ''}
        onChange={(v) => update('headlineLetterSpacing', v)}
        placeholder="e.g. 0.02em, 1px, -0.5px"
        helperText="Leave empty for default"
      />
      <SelectField
        label="Title Text Transform"
        value={(data.headlineTextTransform as string) || ''}
        onChange={(v) => update('headlineTextTransform', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'uppercase', label: 'UPPERCASE' },
          { value: 'lowercase', label: 'lowercase' },
          { value: 'capitalize', label: 'Capitalize' },
        ]}
      />
      <TextField
        label="Title Text Shadow"
        value={(data.headlineTextShadow as string) || ''}
        onChange={(v) => update('headlineTextShadow', v)}
        placeholder="e.g. 2px 2px 4px rgba(0,0,0,0.3)"
        helperText="CSS text-shadow. Leave empty for none."
      />
      <ResponsiveField
        data={data}
        dataKey="subheadlineSize"
        label="Subtitle Size"
        breakpoint={responsiveBp}
        onBreakpointChange={setResponsiveBp}
        onClearOverride={clearOverride('subheadlineSize')}
      >
        <TextField
          label=""
          value={rv('subheadlineSize') || '1.25rem'}
          onChange={(v) => updateResponsive('subheadlineSize', v)}
          placeholder="e.g. 1.25rem, 20px"
          helperText="Font size for the subtitle text"
        />
      </ResponsiveField>
      <SelectField
        label="Subtitle Weight"
        value={(data.subheadlineWeight as string) || '400'}
        onChange={(v) => update('subheadlineWeight', v)}
        options={[
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
          { value: '800', label: 'Extra Bold (800)' },
          { value: '900', label: 'Black (900)' },
        ]}
      />
      <ColorField
        label="Subtitle Color"
        value={(data.subtitleColor as string) || ''}
        onChange={(v) => update('subtitleColor', v)}
      />
      <TextField
        label="Subtitle Line Height"
        value={(data.subheadlineLineHeight as string) || ''}
        onChange={(v) => update('subheadlineLineHeight', v)}
        placeholder="e.g. 1.4, 1.6, 28px"
        helperText="Leave empty for default"
      />
      <TextField
        label="Subtitle Letter Spacing"
        value={(data.subheadlineLetterSpacing as string) || ''}
        onChange={(v) => update('subheadlineLetterSpacing', v)}
        placeholder="e.g. 0.02em, 1px"
        helperText="Leave empty for default"
      />
      <SelectField
        label="Subtitle Text Transform"
        value={(data.subheadlineTextTransform as string) || ''}
        onChange={(v) => update('subheadlineTextTransform', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'uppercase', label: 'UPPERCASE' },
          { value: 'lowercase', label: 'lowercase' },
          { value: 'capitalize', label: 'Capitalize' },
        ]}
      />
      <TextField
        label="Subtitle Text Shadow"
        value={(data.subheadlineTextShadow as string) || ''}
        onChange={(v) => update('subheadlineTextShadow', v)}
        placeholder="e.g. 1px 1px 3px rgba(0,0,0,0.2)"
        helperText="CSS text-shadow. Leave empty for none."
      />
      <ToggleField
        label="Auto Text Color"
        value={data.adaptiveTextColor !== false}
        onChange={(v) => update('adaptiveTextColor', v)}
        helperText="When enabled, title and subtitle automatically use light text on dark backgrounds"
      />

      {/* ── CTA / Link Fields — CTA Button ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Call to Action — CTA Button</div>
      <TextField
        label="Button Text"
        value={(data.ctaText as string) || ''}
        onChange={(v) => update('ctaText', v)}
        placeholder="e.g. Learn More"
      />
      <TextField
        label="Button URL"
        value={(data.ctaUrl as string) || ''}
        onChange={(v) => update('ctaUrl', v)}
        placeholder="https://..."
        helperText="Leave empty to hide the button"
      />

      {/* ── CTA Button Style ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">CTA Button Style</div>
      <SelectField
        label="Variant"
        value={(data.ctaVariant as string) || 'filled'}
        onChange={(v) => update('ctaVariant', v)}
        options={[
          { value: 'filled', label: 'Filled' },
          { value: 'outline', label: 'Outline' },
          { value: 'ghost', label: 'Ghost' },
          { value: 'link', label: 'Link' },
        ]}
      />
      <SelectField
        label="Size"
        value={(data.ctaSize as string) || 'md'}
        onChange={(v) => update('ctaSize', v)}
        options={[
          { value: 'sm', label: 'Small' },
          { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Large' },
        ]}
      />
      <SelectField
        label="Alignment"
        value={(data.ctaAlign as string) || ''}
        onChange={(v) => update('ctaAlign', v)}
        options={[
          { value: '', label: 'Follow Text Alignment' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />
      <ColorField
        label="Background Color"
        value={(data.ctaBgColor as string) || ''}
        onChange={(v) => update('ctaBgColor', v)}
      />
      <ColorField
        label="Text Color"
        value={(data.ctaTextColor as string) || ''}
        onChange={(v) => update('ctaTextColor', v)}
      />
      <ColorField
        label="Border Color"
        value={(data.ctaBorderColor as string) || ''}
        onChange={(v) => update('ctaBorderColor', v)}
      />
      <TextField
        label="Border Width"
        value={(data.ctaBorderWidth as string) || ''}
        onChange={(v) => update('ctaBorderWidth', v)}
        placeholder="e.g. 2px"
        helperText="Leave empty for default"
      />
      <CornerRadiusField
        label="Border Radius"
        value={asObj(data.ctaBorderRadius)}
        onChange={(v) => update('ctaBorderRadius', v)}
        helperText="Applies to CTA button corners"
      />
      <ShadowField
        label="Shadow"
        mode={(data.ctaShadowMode as string) || 'preset'}
        preset={(data.ctaShadow as string) || ''}
        custom={(data.ctaShadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('ctaShadowMode', v)}
        onChangePreset={(v) => update('ctaShadow', v)}
        onChangeCustom={(v) => update('ctaShadowCustom', { ...((data.ctaShadowCustom as ShadowCustom) || {}), ...v })}
      />
      <ColorField
        label="Hover Background"
        value={(data.ctaHoverBgColor as string) || ''}
        onChange={(v) => update('ctaHoverBgColor', v)}
      />
      <ColorField
        label="Hover Text Color"
        value={(data.ctaHoverTextColor as string) || ''}
        onChange={(v) => update('ctaHoverTextColor', v)}
      />
      <ColorField
        label="Hover Border Color"
        value={(data.ctaHoverBorderColor as string) || ''}
        onChange={(v) => update('ctaHoverBorderColor', v)}
      />

      {/* ── Section Border & Shadow — Whole Hero Section ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Border & Shadow — Whole Hero Section</div>
      <TextField
        label="Border Width"
        value={(data.sectionBorderWidth as string) || ''}
        onChange={(v) => update('sectionBorderWidth', v)}
        placeholder="e.g. 1px, 2px"
        helperText="Leave empty for no border"
      />
      <ColorField
        label="Border Color"
        value={(data.sectionBorderColor as string) || ''}
        onChange={(v) => update('sectionBorderColor', v)}
      />
      <SelectField
        label="Border Style"
        value={(data.sectionBorderStyle as string) || ''}
        onChange={(v) => update('sectionBorderStyle', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'solid', label: 'Solid' },
          { value: 'dashed', label: 'Dashed' },
          { value: 'dotted', label: 'Dotted' },
        ]}
      />
      <CornerRadiusField
        label="Border Radius"
        value={asObj(data.sectionBorderRadius)}
        onChange={(v) => update('sectionBorderRadius', v)}
        helperText="Applies to whole Hero section. 50% creates a pill/circle shape."
      />
      <ShadowField
        label="Shadow"
        mode={(data.sectionShadowMode as string) || 'preset'}
        preset={(data.sectionShadow as string) || ''}
        custom={(data.sectionShadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('sectionShadowMode', v)}
        onChangePreset={(v) => update('sectionShadow', v)}
        onChangeCustom={(v) => update('sectionShadowCustom', { ...((data.sectionShadowCustom as ShadowCustom) || {}), ...v })}
      />

      {/* ── Content Box — Text Readability Layer ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Content Box — Text Readability Layer</div>
      <ToggleField
        label="Enable Content Box"
        value={data.contentBoxEnabled === true}
        onChange={(v) => update('contentBoxEnabled', v)}
      />
      {data.contentBoxEnabled === true && (
        <>
          <ColorField
            label="Background Color"
            value={(data.contentBoxBgColor as string) || '#ffffff'}
            onChange={(v) => update('contentBoxBgColor', v)}
          />
          <div>
            <label className="block text-[11px] font-medium text-base-content/50 mb-1">
              Opacity: {Number(data.contentBoxOpacity ?? 80)}%
            </label>
            <input
              type="range"
              min={0}
              max={100}
              value={Number(data.contentBoxOpacity ?? 80)}
              onChange={(e) => update('contentBoxOpacity', Number(e.target.value))}
              className="range range-xs range-primary w-full"
            />
          </div>
          <CornerRadiusField
            label="Border Radius"
            value={asObj(data.contentBoxBorderRadius)}
            onChange={(v) => update('contentBoxBorderRadius', v)}
            helperText="Applies to Content Box corners"
          />
          <ColorField
            label="Border Color"
            value={(data.contentBoxBorderColor as string) || ''}
            onChange={(v) => update('contentBoxBorderColor', v)}
          />
          <TextField
            label="Border Width"
            value={(data.contentBoxBorderWidth as string) || ''}
            onChange={(v) => update('contentBoxBorderWidth', v)}
            placeholder="e.g. 1px"
          />
          <ShadowField
            label="Shadow"
            mode={(data.contentBoxShadowMode as string) || 'preset'}
            preset={(data.contentBoxShadow as string) || ''}
            custom={(data.contentBoxShadowCustom as ShadowCustom) || {}}
            onChangeMode={(v) => update('contentBoxShadowMode', v)}
            onChangePreset={(v) => update('contentBoxShadow', v)}
            onChangeCustom={(v) => update('contentBoxShadowCustom', { ...((data.contentBoxShadowCustom as ShadowCustom) || {}), ...v })}
          />
          <BoxSpacingField
            label="Padding"
            value={asObj(data.contentBoxPadding)}
            onChange={(v) => update('contentBoxPadding', v)}
            placeholder="2rem"
            helperText="Applies to Content Box only"
          />
        </>
      )}

      {/* ── Accessibility Fields ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Accessibility</div>
      <TextField
        label="Background Alt Text"
        value={(data.alt as string) || ''}
        onChange={(v) => update('alt', v)}
        placeholder="Describe the background image for screen readers"
        helperText="Recommended when using a background image"
      />

      {/* mediaLoading: reserved for future <img>/<video> element; Hero uses CSS background */}

    </div>
  );
};
