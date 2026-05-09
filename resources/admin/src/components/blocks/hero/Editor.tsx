import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { TextField, SelectField, ToggleField, ColorField } from '@/components/editor/fields';

export const HeroEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

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

      {/* ── Layout ── */}
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
      <SelectField
        label="Text Alignment"
        value={(data.textAlignment as string) || 'center'}
        onChange={(v) => update('textAlignment', v)}
        options={[
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />
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
      <SelectField
        label="Section Height"
        value={(data.sectionHeight as string) || 'md'}
        onChange={(v) => update('sectionHeight', v)}
        options={[
          { value: 'auto', label: 'Auto' },
          { value: 'sm', label: 'Small (300px)' },
          { value: 'md', label: 'Medium (400px)' },
          { value: 'lg', label: 'Large (600px)' },
          { value: 'fullscreen', label: 'Full Screen' },
        ]}
      />
      <TextField
        label="Content Max Width"
        value={(data.contentMaxWidth as string) || '800px'}
        onChange={(v) => update('contentMaxWidth', v)}
        placeholder="e.g. 800px, 60rem"
      />

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

      {/* ── Accessibility Fields ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Accessibility</div>
      <TextField
        label="Background Alt Text"
        value={(data.alt as string) || ''}
        onChange={(v) => update('alt', v)}
        placeholder="Describe the background image for screen readers"
        helperText="Required when using a background image"
      />

      {/* ── Performance ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Performance</div>
      <SelectField
        label="Image Loading"
        value={(data.mediaLoading as string) || 'eager'}
        onChange={(v) => update('mediaLoading', v)}
        options={[
          { value: 'eager', label: 'Eager (above fold)' },
          { value: 'lazy', label: 'Lazy' },
        ]}
      />

    </div>
  );
};
