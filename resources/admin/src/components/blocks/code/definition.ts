import type { BlockDefinition } from '@/types/blocks';

export const codeDefinition: BlockDefinition = {
  type: 'code',
  category: 'content',
  label: 'Code',
  icon: 'Code',
  defaultData: {
    code: '',
    language: 'javascript',
    show_line_numbers: false,
  },
  allowsChildren: false,
};
