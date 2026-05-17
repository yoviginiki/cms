import type { BlockDefinition } from '@/types/blocks';
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const ctabannerInlineEditing: InlineEditingConfig = {
  blockType: 'ctabanner',
  fields: [
    defineInlineField({
      key: 'heading',
      label: 'Heading',
      placeholder: 'Add heading',
      as: 'h3',
    }),
    defineInlineField({
      key: 'text',
      label: 'Description',
      type: 'multiline',
      placeholder: 'Add description...',
      as: 'p',
    }),
    defineInlineField({
      key: 'buttonText',
      label: 'Button Text',
      placeholder: 'Button text',
    }),
  ],
};

export const ctabannerDefinition: BlockDefinition = {
  type: 'ctabanner',
  category: 'interactive',
  label: 'CTA Banner',
  icon: 'Megaphone',
  hasTypography: true,
  defaultData: {
    heading: 'Ready to get started?',
    text: '',
    buttonText: 'Get started',
    buttonUrl: '#',
    backgroundStyle: 'solid',
    backgroundColor: '',
    backgroundImage: '',
    headingTextShadow: '',
  },
  allowsChildren: false,
};
