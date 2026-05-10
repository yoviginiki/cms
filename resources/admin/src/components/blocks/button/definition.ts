import type { BlockDefinition } from '@/types/blocks';
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const buttonInlineEditing: InlineEditingConfig = {
  blockType: 'button',
  fields: [
    defineInlineField({
      key: 'text',
      label: 'Button Text',
      placeholder: 'Button text',
    }),
  ],
};

export const buttonDefinition: BlockDefinition = {
  type: 'button',
  category: 'content',
  label: 'Button',
  icon: '🔘',
  defaultData: {
    text: 'Click Me',
    url: '#',
    style: 'primary',
    size: 'md',
    target: '_self',
    iconLeft: '',
    iconRight: '',
    fullWidth: false,
  },
  allowsChildren: false,
};
