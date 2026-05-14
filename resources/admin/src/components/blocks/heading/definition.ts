import type { BlockDefinition } from '@/types/blocks';
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const headingInlineEditing: InlineEditingConfig = {
  blockType: 'heading',
  fields: [
    defineInlineField({
      key: 'text',
      label: 'Heading Text',
      placeholder: 'Add heading',
      as: 'h2',
    }),
  ],
};

export const headingDefinition: BlockDefinition = {
  type: 'heading',
  category: 'content',
  label: 'Heading',
  icon: 'Heading',
  defaultData: {
    text: 'Heading',
    level: 'h2',
    // Typography
    color: '',
    fontSize: '',
    fontWeight: '',
    lineHeight: '',
    letterSpacing: '',
    textTransform: '',
    textAlign: '',
  },
  allowsChildren: false,
  hasTypography: true,
};
