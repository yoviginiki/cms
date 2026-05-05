import type { BlockDefinition } from '@/types/blocks';

export const footnoteDefinition: BlockDefinition = {
  type: 'footnote',
  category: 'typography',
  label: 'Footnote',
  icon: 'Asterisk',
  description: 'Footnote with superscript marker',
  defaultData: {
    content: '',
    marker: '',
  },
  allowsChildren: false,
  hasTypography: true,
};
