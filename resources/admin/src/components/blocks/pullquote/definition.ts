import type { BlockDefinition } from '@/types/blocks';

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
