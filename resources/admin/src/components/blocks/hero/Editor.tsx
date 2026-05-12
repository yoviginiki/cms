import React, { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { TextField, SelectField, ToggleField, ColorField, ShadowField } from '@/components/editor/fields';
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

      {/* ── Layout (with responsive overrides) ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Layout</div>
      <SelectField
        label="Heading Tag"
        value={(data.headlineTag as string) || 'h1'}
        onChange={(v) => update('headlineTag', v)}
        options={[
          { value: 'h1', label: 'H1' },
          { value: 'h2', label: 'H2' },
          { value: 'h3', label: 'H3' },
        ]}
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

      {/* ── Background (settings panel — managed by BackgroundEditor) ── */}
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      {/* ── Typography ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <TextField
        label="Headline Size"
        value={(data.headlineSize as string) || '2.5rem'}
        onChange={(v) => update('headlineSize', v)}
        placeholder="e.g. 2.5rem, 48px"
      />
      <SelectField
        label="Headline Weight"
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
      <TextField
        label="Subheadline Size"
        value={(data.subheadlineSize as string) || '1.25rem'}
        onChange={(v) => update('subheadlineSize', v)}
        placeholder="e.g. 1.25rem, 20px"
      />
      <ColorField
        label="Headline Color"
        value={(data.headlineColor as string) || ''}
        onChange={(v) => update('headlineColor', v)}
      />
      <ToggleField
        label="Auto Text Color"
        value={data.adaptiveTextColor !== false}
        onChange={(v) => update('adaptiveTextColor', v)}
      />

      {/* ── CTA / Link Fields ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Call to Action</div>
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
      <TextField
        label="Border Radius"
        value={(data.ctaBorderRadius as string) || ''}
        onChange={(v) => update('ctaBorderRadius', v)}
        placeholder="e.g. 0.375rem, 8px"
        helperText="Leave empty for default"
      />

      {/* ── Section Border & Shadow ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Border & Shadow</div>
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
      <TextField
        label="Border Radius"
        value={(data.sectionBorderRadius as string) || ''}
        onChange={(v) => update('sectionBorderRadius', v)}
        placeholder="e.g. 0.75rem, 12px"
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

      {/* ── Content Box / Text Readability ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Content Box</div>
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
          <TextField
            label="Border Radius"
            value={(data.contentBoxBorderRadius as string) || '0.75rem'}
            onChange={(v) => update('contentBoxBorderRadius', v)}
            placeholder="e.g. 0.75rem, 8px"
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
          <SelectField
            label="Shadow"
            value={(data.contentBoxShadow as string) || ''}
            onChange={(v) => update('contentBoxShadow', v)}
            options={[
              { value: '', label: 'None' },
              { value: 'sm', label: 'Small' },
              { value: 'md', label: 'Medium' },
              { value: 'lg', label: 'Large' },
            ]}
          />
          <TextField
            label="Padding"
            value={(data.contentBoxPadding as string) || '2rem'}
            onChange={(v) => update('contentBoxPadding', v)}
            placeholder="e.g. 2rem, 32px"
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
