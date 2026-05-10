import type { BlockDefinition } from '@/types/blocks';
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const pullquoteInlineEditing: InlineEditingConfig = {
  blockType: 'pullquote',
  fields: [
    defineInlineField({
      key: 'text',
      label: 'Quote Text',
      type: 'multiline',
      placeholder: 'Add a quote...',
      as: 'p',
    }),
    defineInlineField({
      key: 'attribution',
      label: 'Attribution',
      placeholder: 'Author or source',
    }),
  ],
};

export const pullquoteDefinition: BlockDefinition = {
  type: 'pullquote',
  category: 'typography',
  label: 'Pull Quote',
  icon: 'Quote',
  description: 'Large decorative quote for emphasis',
  defaultData: {
    text: '',
    attribution: '',
    style: 'large-text',
  },
  allowsChildren: false,
  hasTypography: true,
};
