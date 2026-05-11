import type { BlockDefinition } from '@/types/blocks';
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const heroInlineEditing: InlineEditingConfig = {
  blockType: 'hero',
  fields: [
    defineInlineField({
      key: 'title',
      label: 'Hero Title',
      placeholder: 'Add hero title',
      as: 'h1',
    }),
    defineInlineField({
      key: 'subtitle',
      label: 'Subtitle',
      placeholder: 'Add subtitle',
      as: 'p',
    }),
    defineInlineField({
      key: 'ctaText',
      label: 'Button Text',
      placeholder: 'Add button text',
    }),
  ],
};

export const heroDefinition: BlockDefinition = {
  type: 'hero',
  category: 'marketing',
  label: 'Hero Section',
  icon: 'Layout',
  description: 'Full-width hero banner with title, subtitle, background, and call-to-action',
  defaultData: {
    // Content fields (inline editable candidates)
    title: 'Hero Title',
    subtitle: '',

    // Background fields (settings panel, managed by BackgroundEditor)
    bg_type: 'none',
    bg_color: '',
    bg_gradient_type: 'linear',
    bg_gradient_angle: 180,
    bg_gradient_stops: [
      { color: '#3b82f6', position: 0 },
      { color: '#8b5cf6', position: 100 },
    ],
    bg_image: '',
    bg_asset_id: '',            // optional asset library reference
    bg_image_size: 'cover',
    bg_image_position: 'center center',
    bg_image_repeat: 'no-repeat',
    bg_overlay_color: '#000000',
    bg_overlay_opacity: 0,
    bg_scroll_effect: 'none',
    bg_parallax_speed: 0.5,

    // Layout fields
    headlineTag: 'h1',          // h1 | h2 | h3
    textAlignment: 'center',    // left | center | right
    verticalPosition: 'center', // top | center | bottom
    sectionHeight: 'md',        // auto | sm | md | lg | fullscreen
    contentMaxWidth: '800px',

    // Typography fields
    headlineSize: '2.5rem',
    headlineWeight: '700',
    headlineColor: '',           // empty = auto-derive from bg
    subheadlineSize: '1.25rem',
    adaptiveTextColor: true,

    // CTA / Link fields (settings panel)
    ctaText: '',
    ctaUrl: '',

    // CTA button style fields (optional — safe defaults when empty)
    ctaVariant: 'filled',       // filled | outline | ghost | link
    ctaSize: 'md',              // sm | md | lg
    ctaAlign: '',               // '' (follow textAlignment) | left | center | right
    ctaBgColor: '',             // safe CSS color
    ctaTextColor: '',           // safe CSS color
    ctaBorderColor: '',         // safe CSS color
    ctaBorderWidth: '',         // safe CSS dimension e.g. 2px
    ctaBorderRadius: '',        // safe CSS dimension e.g. 0.375rem

    // Content box / text readability layer (optional)
    contentBoxEnabled: false,
    contentBoxBgColor: '#ffffff',
    contentBoxOpacity: 80,           // 0-100
    contentBoxBorderRadius: '0.75rem',
    contentBoxBorderColor: '',
    contentBoxBorderWidth: '',
    contentBoxShadow: '',            // none | sm | md | lg
    contentBoxPadding: '2rem',

    // Responsive overrides (optional — tablet/mobile inherit from desktop base)
    // responsive: { tablet: {}, mobile: {} }

    // Accessibility fields
    alt: '',

    // Performance fields
    mediaLoading: 'eager',       // eager | lazy
  },
  allowsChildren: false,
};
